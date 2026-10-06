<?php

namespace App\Services\Owners;

use App\Models\Property;
use App\Models\PropertyListing;

class ListingCompletenessService
{
    public function evaluate(PropertyListing $listing): array
    {
        $data = $listing->property_data ?? [];
        $checks = [
            'identity' => filled($data['name'] ?? null) && filled($data['property_type'] ?? null),
            'address' => filled($data['location'] ?? null) || filled($data['address_line_1'] ?? null),
            'capacity' => (int) ($data['max_guests'] ?? 0) > 0,
            'description' => mb_strlen(trim((string) ($data['description'] ?? $data['short_description'] ?? ''))) >= 30,
            'media' => filled($listing->cover_image) || count($listing->gallery ?? []) > 0,
            'amenities' => count($listing->amenity_ids ?? []) > 0,
            'agreement' => filled($listing->listing_agreement_id),
            'payout_profile' => $listing->user?->ownerPayoutProfile !== null,
        ];

        $completed = collect($checks)->filter()->count();
        $score = (int) round(($completed / max(1, count($checks))) * 100);
        $blockers = collect($checks)
            ->filter(fn (bool $complete) => ! $complete)
            ->keys()
            ->values()
            ->all();

        return [
            'score' => $score,
            'checks' => $checks,
            'blockers' => $blockers,
            'publishable' => $blockers === [],
        ];
    }

    public function sync(PropertyListing $listing): array
    {
        $listing->loadMissing('user.ownerPayoutProfile');
        $result = $this->evaluate($listing);

        $listing->update([
            'completeness_score' => $result['score'],
            'completion_snapshot' => $result['checks'],
            'publication_blockers' => $result['blockers'],
            'last_completed_at' => $result['publishable'] ? now() : null,
        ]);

        return $result;
    }

    public function propertyPublishability(Property $property): array
    {
        $property->loadMissing(['amenities', 'accommodationTypes.ratePlans']);

        $checks = [
            'identity' => filled($property->name) && filled($property->property_type),
            'address' => filled($property->formatted_address) || filled($property->address_line_1) || filled($property->location),
            'coordinates' => $property->latitude !== null && $property->longitude !== null,
            'description' => mb_strlen(trim((string) $property->description)) >= 30,
            'media' => filled($property->cover_image) || count($property->gallery ?? []) > 0,
            'amenities' => $property->amenities->isNotEmpty(),
            'accommodation' => $property->accommodationTypes->contains(fn ($type) => $type->is_active && $type->total_inventory > 0),
            'rate_plan' => $property->accommodationTypes->contains(fn ($type) => $type->ratePlans->contains(fn ($plan) => $plan->is_active)),
        ];

        $blockers = collect($checks)->filter(fn (bool $complete) => ! $complete)->keys()->values()->all();

        return [
            'score' => (int) round((collect($checks)->filter()->count() / count($checks)) * 100),
            'checks' => $checks,
            'blockers' => $blockers,
            'publishable' => $blockers === [],
        ];
    }
}
