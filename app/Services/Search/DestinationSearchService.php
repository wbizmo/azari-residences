<?php

namespace App\Services\Search;

use App\Models\Location;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DestinationSearchService
{
    public function suggest(string $rawQuery, int $limit = 8): Collection
    {
        $query = $this->normalize($rawQuery);
        $limit = max(1, min($limit, 10));

        if (mb_strlen($query) < 2) {
            return collect();
        }

        $prefix = $query.'%';
        $perType = max(2, (int) ceil($limit / 2));

        $locations = Location::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $builder) => $this->publishedProperties($builder))
            ->where(function (Builder $builder) use ($prefix): void {
                $builder
                    ->where('name', 'like', $prefix)
                    ->orWhere('city', 'like', $prefix)
                    ->orWhere('country', 'like', $prefix);
            })
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 WHEN city LIKE ? THEN 2 ELSE 3 END',
                [$query, $prefix, $prefix]
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($perType)
            ->get(['id', 'name', 'city', 'country'])
            ->map(fn (Location $location) => [
                'type' => 'location',
                'id' => $location->getKey(),
                'label' => $location->name,
                'secondary' => collect([$location->city, $location->country])->filter()->unique()->join(', '),
                'value' => $location->name,
            ]);

        $properties = Property::query()
            ->with('locationRecord:id,name,city,country')
            ->where(fn (Builder $builder) => $this->publishedProperties($builder))
            ->where('name', 'like', $prefix)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($perType)
            ->get(['id', 'name', 'slug', 'location_id', 'location', 'country'])
            ->map(fn (Property $property) => [
                'type' => 'property',
                'id' => $property->getKey(),
                'label' => $property->name,
                'secondary' => $property->locationRecord
                    ? collect([$property->locationRecord->name, $property->locationRecord->city])->filter()->unique()->join(', ')
                    : collect([$property->location, $property->country])->filter()->unique()->join(', '),
                'value' => $property->name,
            ]);

        $cities = Location::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $builder) => $this->publishedProperties($builder))
            ->where('city', 'like', $prefix)
            ->whereNotNull('city')
            ->orderBy('city')
            ->limit(3)
            ->get(['city', 'country'])
            ->unique(fn (Location $location) => mb_strtolower($location->city.'|'.$location->country))
            ->map(fn (Location $location) => [
                'type' => 'city',
                'id' => null,
                'label' => $location->city,
                'secondary' => $location->country,
                'value' => $location->city,
            ]);

        return $locations
            ->concat($properties)
            ->concat($cities)
            ->unique(fn (array $item) => $item['type'].'|'.($item['id'] ?? mb_strtolower($item['value'])))
            ->take($limit)
            ->values();
    }

    public function normalize(string $query): string
    {
        $query = Str::squish($query);
        $query = preg_replace('/[%_\\\\]+/', '', $query) ?? '';

        return mb_substr(trim($query), 0, 80);
    }

    private function publishedProperties(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $status): void {
                $status
                    ->whereNull('status')
                    ->orWhereNotIn('status', ['inactive', 'unavailable', 'maintenance', 'archived']);
            });
    }
}
