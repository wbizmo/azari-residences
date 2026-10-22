<?php

namespace App\Services\Owners;

use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Read-only launch inventory inspection. Dates are actual occupied nights,
 * exclusive of checkout, and availability/quotes use the canonical engines.
 * A published record is NOT a promise that a date can be sold.
 */
final class SupplyReadinessService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
        private readonly ListingCompletenessService $completeness,
    ) {}

    public function inspect(Property $property, CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $property->loadMissing([
            'amenities', 'ownerAgreement', 'ownerListing',
            'photoModerations', 'accommodationTypes.ratePlans.paymentPolicy',
            'accommodationTypes.ratePlans.cancellationPolicy',
        ]);

        $blockers = $this->completeness->propertyPublishability($property)['blockers'];
        $review = $property->publicationReviews()->latest('id')->first();
        $reviewed = in_array($review?->status, ['approved_draft', 'published'], true);
        if (! $reviewed) {
            $blockers[] = 'No current approved publication review';
        }
        if (! $property->is_published || $property->status !== 'available') {
            $blockers[] = 'Not publicly available';
        }

        $paths = collect([$property->cover_image])->merge($property->gallery ?? [])->filter()->unique();
        if ($paths->isEmpty() || blank($property->cover_image)) {
            $blockers[] = 'Missing cover image';
        }
        $moderations = $property->photoModerations->keyBy('path');
        foreach ($paths as $path) {
            $photo = $moderations->get($path);
            if ((! str_starts_with($path, 'properties/') && ! str_starts_with($path, 'owner-listings/'))
                || ! $photo || $photo->status !== 'approved'
                || blank($photo->alt_text) || blank($photo->attribution)
                || ! Storage::disk('public')->exists($path)) {
                $blockers[] = 'Photo moderation, rights or file missing';
                break;
            }
        }

        if ($property->owner_id) {
            $agreement = $property->ownerAgreement;
            if (! $agreement || (int) $agreement->user_id !== (int) $property->owner_id || ! $agreement->signed_at) {
                $blockers[] = 'Signed owner agreement missing';
            }
            $listing = $property->ownerListing;
            if ($listing && $listing->operator_type !== null) {
                $proof = $listing->controlDocuments()->latest('id')->first();
                if (! $proof || $proof->status !== 'verified' || ! $proof->reviewed_at
                    || \Carbon\Carbon::parse($proof->reviewed_at)->lt(now()->subYear())
                    || ! Storage::disk('private')->exists($proof->path)) {
                    $blockers[] = 'Verified owner-control document missing or stale';
                }
            }
        }
        if (blank($property->currency) || $property->tax_rate === null || (float) $property->tax_rate < 0) {
            $blockers[] = 'Currency or tax disclosure missing';
        }

        $hasValidRatePolicy = false;
        foreach ($property->accommodationTypes as $type) {
            if (! $type->is_active || ! $type->is_published || $type->total_inventory < 1) {
                continue;
            }
            foreach ($type->ratePlans as $plan) {
                if (! $plan->is_active || ! $plan->is_public) {
                    continue;
                }
                $policiesValid = $plan->paymentPolicy?->is_active
                    && (int) $plan->paymentPolicy->property_id === (int) $property->id
                    && $plan->cancellationPolicy?->is_active
                    && (int) $plan->cancellationPolicy->property_id === (int) $property->id;
                if ($policiesValid) {
                    $hasValidRatePolicy = true;
                }
            }
        }
        if (! $hasValidRatePolicy) {
            $blockers[] = 'No active public rate with valid payment/cancellation policies';
        }

        // Blocking structural problems must not be disguised by an available
        // date or a quote from a different/incorrect room-rate combination.
        $blockers = array_values(array_unique($blockers));
        $readyForDates = false;
        $quote = null;
        if ($blockers === []) {
            foreach ($property->accommodationTypes as $type) {
                if (! $type->is_active || ! $type->is_published || $type->total_inventory < 1) {
                    continue;
                }
                foreach ($type->ratePlans as $plan) {
                    if (! $plan->is_active || ! $plan->is_public || ! $plan->paymentPolicy?->is_active
                        || (int) $plan->paymentPolicy->property_id !== (int) $property->id
                        || ! $plan->cancellationPolicy?->is_active
                        || (int) $plan->cancellationPolicy->property_id !== (int) $property->id) {
                        continue;
                    }
                    try {
                        $this->availability->assertRules($property, $checkIn, $checkOut, 1, 0, 1, $type, $plan);
                        if (! $this->availability->availableForProperty(
                            $property, $checkIn, $checkOut, 1, $type->id
                        )) {
                            continue;
                        }
                        $candidate = $this->pricing->quote($property, $checkIn, $checkOut, [], $type, $plan);
                        if ((float) ($candidate['total'] ?? 0) <= 0) {
                            continue;
                        }
                        $readyForDates = true;
                        $quote = [
                            'currency' => $candidate['currency'],
                            'total' => $candidate['total'],
                            'accommodation_type_id' => $type->id,
                            'rate_plan_id' => $plan->id,
                        ];
                        break 2;
                    } catch (ValidationException) {
                        // Occupancy, restrictions or blackout dates make this
                        // particular type/rate unavailable. Try the next one.
                    }
                }
            }
            if (! $readyForDates) {
                $blockers[] = 'No canonical availability and positive quote for these dates';
            }
        }

        $state = ! $property->is_published
            ? ($review?->status === 'approved_draft' ? 'reviewed' : 'draft')
            : ($readyForDates ? 'bookable' : 'published_not_bookable');

        return [
            'state' => $state,
            'ready' => $readyForDates,
            'blockers' => $blockers,
            'quote' => $quote,
            'verified_claims' => $property->publicVerifiedClaims()->count(),
            'location' => $property->locationRecord?->name ?? $property->location ?? 'Not assigned',
        ];
    }
}
