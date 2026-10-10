<?php

namespace App\Services\PhaseThree;

use App\Models\RecentlyViewedProperty;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Search\MarketplaceSearchService;

/** Rank only canonical, still-bookable quoted results. No behavioral signals across accounts. */
final class PrivacyAwareRecommendationService
{
    public function __construct(private readonly MarketplaceSearchService $search) {}

    public function forGuest(User $guest, array $filters): array
    {
        $params = collect($filters)->only(['check_in','check_out','adults','children','rooms',
            'destination','property_id','amenities'])->all();
        $results = $this->search->search($params, false)['results']->getCollection();
        $recent = [];
        $savedAmenities = [];
        if (! $guest->personalization_opt_out) {
            $recent = RecentlyViewedProperty::query()->where('user_id', $guest->getKey())
                ->orderByDesc('viewed_at')->limit(20)->pluck('property_id')->all();
            $saved = SavedSearch::query()->where('user_id', $guest->getKey())
                ->orderByDesc('last_used_at')->limit(10)->get(['parameters']);
            $savedAmenities = $saved->flatMap(fn ($row) => $row->parameters['amenities'] ?? [])
                ->map(fn ($id) => (int) $id)->filter()->unique()->all();
        }
        return $results->map(function (array $item) use ($recent, $savedAmenities, $guest): array {
            $property = $item['property'];
            $score = 0;
            $reasons = [];
            if (! $guest->personalization_opt_out && in_array($property->id, $recent, true)) {
                $score += 2; $reasons[] = 'Recently viewed by you';
            }
            if (! $guest->personalization_opt_out && $savedAmenities !== []) {
                $matches = $property->amenities->pluck('id')->intersect($savedAmenities)->count();
                if ($matches > 0) {
                    $score += min(3, $matches); $reasons[] = 'Matches saved amenities';
                }
            }
            if (! $reasons) $reasons[] = 'Available for your selected dates';
            return ['property_id' => $property->id, 'name' => $property->name,
                'url' => route('properties.show', $property),
                'currency' => $item['quote']['currency'], 'total' => $item['quote']['total'],
                'score' => $score, 'reason' => implode('; ', $reasons)];
        })->sortBy([['score','desc'], ['property_id','asc']])->take(12)->values()->all();
    }
}
