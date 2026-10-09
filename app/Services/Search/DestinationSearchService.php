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
        $limit = max(1, min($limit, (int) config('reserva.search.autocomplete_max_limit', 10)));

        if (mb_strlen($query) < (int) config('reserva.search.autocomplete_min_chars', 2)) {
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

        $results = $locations
            ->concat($properties)
            ->concat($cities)
            ->unique(fn (array $item) => $item['type'].'|'.($item['id'] ?? mb_strtolower($item['value'])))
            ->values();

        if ($results->count() < $limit) {
            $results = $results
                ->concat($this->fuzzyFallback($query, $limit))
                ->unique(fn (array $item) => $item['type'].'|'.($item['id'] ?? mb_strtolower($item['value'])))
                ->values();
        }

        return $results->take($limit)->values();
    }

    public function normalize(string $query): string
    {
        $query = Str::squish($query);
        $query = preg_replace('/[%_\\\\]+/', '', $query) ?? '';

        return mb_substr(trim($query), 0, 80);
    }

    private function fuzzyFallback(string $query, int $limit): Collection
    {
        $needle = $this->fold($query);
        if (mb_strlen($needle) < 3) {
            return collect();
        }

        $locations = Location::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $builder) => $this->publishedProperties($builder))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'city', 'country'])
            ->flatMap(fn (Location $location) => [
                [
                    'type' => 'location',
                    'id' => $location->id,
                    'label' => $location->name,
                    'secondary' => collect([$location->city, $location->country])->filter()->unique()->join(', '),
                    'value' => $location->name,
                    '_haystacks' => [$location->name, $location->city, $location->country],
                ],
                filled($location->city) ? [
                    'type' => 'city',
                    'id' => null,
                    'label' => $location->city,
                    'secondary' => $location->country,
                    'value' => $location->city,
                    '_haystacks' => [$location->city, $location->country],
                ] : null,
            ])->filter();

        $properties = Property::query()
            ->with('locationRecord:id,name,city,country')
            ->where(fn (Builder $builder) => $this->publishedProperties($builder))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(250)
            ->get(['id', 'name', 'slug', 'location_id', 'location', 'country'])
            ->map(fn (Property $property) => [
                'type' => 'property',
                'id' => $property->id,
                'label' => $property->name,
                'secondary' => $property->locationRecord
                    ? collect([$property->locationRecord->name, $property->locationRecord->city])->filter()->unique()->join(', ')
                    : collect([$property->location, $property->country])->filter()->unique()->join(', '),
                'value' => $property->name,
                '_haystacks' => [$property->name, $property->locationRecord?->name, $property->locationRecord?->city],
            ]);

        return $locations->concat($properties)
            ->map(function (array $item) use ($needle): array {
                $score = collect($item['_haystacks'])
                    ->filter()
                    ->map(fn ($value) => $this->similarity($needle, $this->fold((string) $value)))
                    ->max() ?? 0;

                unset($item['_haystacks']);
                $item['_score'] = $score;

                return $item;
            })
            ->filter(fn (array $item) => $item['_score'] >= 0.58)
            ->sortByDesc('_score')
            ->take(max($limit * 2, 8))
            ->map(function (array $item): array {
                unset($item['_score']);
                return $item;
            })
            ->values();
    }

    private function fold(string $value): string
    {
        return mb_strtolower(Str::ascii(Str::squish($value)));
    }

    private function similarity(string $needle, string $candidate): float
    {
        if ($candidate === '') {
            return 0.0;
        }

        if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
            return 0.95;
        }

        $distance = levenshtein(mb_substr($needle, 0, 80), mb_substr($candidate, 0, 80));
        $length = max(strlen($needle), strlen($candidate), 1);

        return max(0.0, 1.0 - ($distance / $length));
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
