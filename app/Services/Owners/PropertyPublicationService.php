<?php

namespace App\Services\Owners;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** The only supported manual publication transition for accommodations. */
final class PropertyPublicationService
{
    public function __construct(private readonly ListingCompletenessService $completeness) {}

    public function review(Property $property, User $actor, string $note, array $checklist): Property
    {
        return DB::transaction(function () use ($property, $actor, $note, $checklist): Property {
            $locked = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isAdministrator(), 403);
            if ($locked->is_published) {
                throw ValidationException::withMessages(['is_published' => 'Unpublish before a new review.']);
            }
            if ($locked->publicationReviews()->latest('id')->first()?->status === 'approved_draft') {
                throw ValidationException::withMessages([
                    'is_published' => 'This draft is already approved. Publish it or reopen quality review.',
                ]);
            }
            $this->assertPublishable($locked);
            $digest = $this->digest($locked);
            $locked->publicationReviews()->create([
                'reviewed_by' => $actor->id, 'status' => 'approved_draft',
                'review_digest' => $digest, 'review_note' => $note,
                'checklist' => $checklist,
            ]);
            AuditLog::record('property.publication_approved_draft', $locked,
                ['is_published' => false], ['review_digest' => $digest],
                ['reviewer_id' => $actor->id]);
            return $locked->refresh();
        }, 3);
    }

    public function publish(Property $property, User $actor): Property
    {
        return DB::transaction(function () use ($property, $actor): Property {
            $locked = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isAdministrator(), 403);
            $latest = $locked->publicationReviews()->latest('id')->first();
            if ($locked->is_published && $latest?->status === 'published') {
                return $locked; // Idempotent admin retry.
            }
            if ($latest?->status !== 'approved_draft') {
                throw ValidationException::withMessages([
                    'is_published' => 'This property requires a new, successful quality review before publication.',
                ]);
            }
            $this->assertPublishable($locked);
            if (! hash_equals((string) $latest->review_digest, $this->digest($locked))) {
                throw ValidationException::withMessages([
                    'is_published' => 'Property details, policies, owner proof, prices or image moderation changed after approval. Review again.',
                ]);
            }
            $locked->forceFill(['is_published' => true, 'status' => 'available'])->save();
            $locked->publicationReviews()->create([
                'reviewed_by' => $actor->id, 'status' => 'published',
                'review_digest' => $latest->review_digest,
                'review_note' => 'Published from approved quality review #'.$latest->id,
                'checklist' => ['approved_review_id' => $latest->id],
            ]);
            AuditLog::record('property.published', $locked,
                ['is_published' => false], ['is_published' => true],
                ['reviewer_id' => $actor->id]);
            return $locked->refresh();
        }, 3);
    }

    public function unpublish(Property $property, User $actor, string $reason): Property
    {
        return DB::transaction(function () use ($property, $actor, $reason): Property {
            $locked = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isAdministrator(), 403);
            if (! $locked->is_published && $locked->publicationReviews()->latest('id')->first()?->status === 'unpublished') {
                return $locked;
            }
            $before = (bool) $locked->is_published;
            $locked->forceFill(['is_published' => false, 'is_featured' => false, 'status' => 'draft'])->save();
            $locked->publicationReviews()->create([
                'reviewed_by' => $actor->id, 'status' => 'unpublished',
                'review_note' => $reason,
            ]);
            AuditLog::record('property.unpublished', $locked,
                ['is_published' => $before], ['is_published' => false], ['reason' => $reason]);
            return $locked->refresh();
        }, 3);
    }

    /** Call while holding the property lock, when a room or rate changes. */
    public function invalidateForEdit(Property $property, string $reason): void
    {
        $latest = $property->publicationReviews()->latest('id')->first();
        if (! $property->is_published && $latest?->status !== 'approved_draft') {
            return;
        }
        $previous = (bool) $property->is_published;
        $property->forceFill(['is_published' => false, 'is_featured' => false, 'status' => 'draft'])->save();
        $property->publicationReviews()->create(['status' => 'unpublished', 'review_note' => $reason]);
        AuditLog::record('property.publication_invalidated', $property,
            ['is_published' => $previous], ['is_published' => false], ['reason' => $reason]);
    }

    public function assertPublishable(Property $property): void
    {
        $property->unsetRelation('amenities');
        $property->unsetRelation('accommodationTypes');
        $property->loadMissing('accommodationTypes.ratePlans.paymentPolicy', 'accommodationTypes.ratePlans.cancellationPolicy');
        $blockers = $this->completeness->propertyPublishability($property)['blockers'];
        $paths = collect([$property->cover_image])->merge($property->gallery ?? [])->filter()->unique()->values();
        if ($paths->isEmpty() || ! $property->cover_image) {
            $blockers[] = 'cover photo';
        }
        foreach ($paths as $path) {
            $review = $property->photoModerations()
                ->where('path_hash', hash('sha256', $path))->where('path', $path)->first();
            if (! str_starts_with($path, 'properties/') && ! str_starts_with($path, 'owner-listings/')) {
                $blockers[] = 'photo path';
            }
            if (! Storage::disk('public')->exists($path) || ! $review
                || $review->status !== 'approved'
                || mb_strlen(trim((string) $review->alt_text)) < 8
                || mb_strlen(trim((string) $review->attribution)) < 3) {
                $blockers[] = 'photo rights or missing file';
            }
        }
        if ($property->tax_rate === null || (float) $property->tax_rate < 0) {
            $blockers[] = 'tax display';
        }
        $rateFound = false;
        foreach ($property->accommodationTypes as $type) {
            if (! $type->is_active || ! $type->is_published || $type->total_inventory < 1) {
                continue;
            }
            foreach ($type->ratePlans as $plan) {
                if (! $plan->is_active || ! $plan->is_public) {
                    continue;
                }
                $rateFound = true;
                if (! $plan->paymentPolicy || ! $plan->paymentPolicy->is_active
                    || (int) $plan->paymentPolicy->property_id !== (int) $property->id
                    || ! $plan->cancellationPolicy || ! $plan->cancellationPolicy->is_active
                    || (int) $plan->cancellationPolicy->property_id !== (int) $property->id) {
                    $blockers[] = 'payment/cancellation policy for every sellable rate';
                }
            }
        }
        if (! $rateFound) {
            $blockers[] = 'public room/rate inventory';
        }
        if ($property->owner_id) {
            $agreement = $property->ownerAgreement;
            if (! $agreement || (int) $agreement->user_id !== (int) $property->owner_id || ! $agreement->signed_at) {
                $blockers[] = 'signed owner agreement';
            }
            $listing = $property->ownerListing;
            if ($listing && $listing->operator_type !== null) {
                $proof = $listing->controlDocuments()->latest('id')->first();
                if (! $proof || $proof->status !== 'verified'
                    || ! $proof->reviewed_at
                    || \Carbon\Carbon::parse($proof->reviewed_at)->lt(now()->subYear())
                    || ! Storage::disk('private')->exists($proof->path)) {
                    $blockers[] = 'current manually verified proof-of-control document';
                }
            }
        }
        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'is_published' => 'Publication blocked: '.implode(', ', array_unique($blockers)).'.',
            ]);
        }
    }

    private function digest(Property $property): string
    {
        $property->load([
            'amenities', 'accommodationTypes.ratePlans.paymentPolicy',
            'accommodationTypes.ratePlans.cancellationPolicy',
            'photoModerations', 'ownerAgreement',
        ]);
        $typeSnapshot = $property->accommodationTypes->map(fn ($type) => [
            $type->getAttributes(),
            $type->ratePlans->map(fn ($plan) => [
                $plan->getAttributes(),
                $plan->paymentPolicy?->getAttributes(),
                $plan->cancellationPolicy?->getAttributes(),
            ])->all(),
        ])->all();
        return hash('sha256', json_encode([
            $property->only([
                'name', 'property_type', 'formatted_address', 'address_line_1',
                'latitude', 'longitude', 'cover_image', 'gallery', 'description',
                'currency', 'nightly_rate', 'tax_rate', 'owner_id', 'owner_agreement_id',
            ]),
            $property->amenities->pluck('id')->sort()->values()->all(),
            $property->photoModerations->map(fn ($photo) => $photo->only([
                'path', 'status', 'alt_text', 'attribution', 'reviewed_at',
            ]))->all(),
            $typeSnapshot,
            $property->ownerAgreement?->getAttributes(),
            $property->ownerListing?->controlDocuments()->latest('id')->first()?->getAttributes(),
        ], JSON_THROW_ON_ERROR));
    }
}
