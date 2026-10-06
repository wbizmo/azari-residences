<?php

namespace App\Services\Reviews;

use App\Models\Review;
use Illuminate\Support\Collection;

class ReviewSummaryService
{
    public function forProperty(int $propertyId): array
    {
        return $this->forProperties([$propertyId])->get($propertyId, $this->empty());
    }

    public function forProperties(array $propertyIds): Collection
    {
        $ids = collect($propertyIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = Review::query()
            ->whereIn('property_id', $ids)
            ->where('verified_stay', true)
            ->where('status', 'approved')
            ->selectRaw('
                property_id,
                COUNT(*) as review_count,
                AVG(rating) as overall,
                AVG(cleanliness) as cleanliness,
                AVG(comfort) as comfort,
                AVG(facilities) as facilities,
                AVG(location_score) as location_score,
                AVG(staff_service) as staff_service,
                AVG(value_score) as value_score,
                AVG(wifi_score) as wifi_score
            ')
            ->groupBy('property_id')
            ->get()
            ->keyBy('property_id')
            ->map(fn ($row) => [
                'count' => (int) $row->review_count,
                'overall' => round((float) $row->overall, 1),
                'categories' => collect([
                    'cleanliness' => $row->cleanliness,
                    'comfort' => $row->comfort,
                    'facilities' => $row->facilities,
                    'location' => $row->location_score,
                    'staff_service' => $row->staff_service,
                    'value' => $row->value_score,
                    'wifi' => $row->wifi_score,
                ])->filter(fn ($value) => $value !== null)
                    ->map(fn ($value) => round((float) $value, 1))
                    ->all(),
            ]);

        foreach ($ids as $id) {
            if (! $rows->has($id)) {
                $rows->put($id, $this->empty());
            }
        }

        return $rows;
    }

    private function empty(): array
    {
        return ['count' => 0, 'overall' => null, 'categories' => []];
    }
}
