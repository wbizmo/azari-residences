<?php

namespace App\Services\Search;

use App\Models\Amenity;
use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Reviews\ReviewSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceSearchService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
        private readonly ReviewSummaryService $reviews,
    ) {}

    public function search(array $filters): array
    {
        $checkIn = CarbonImmutable::parse($filters['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($filters['check_out'])->startOfDay();
        $rooms = max(1, (int) ($filters['rooms'] ?? 1));
        $guests = max(1, (int) ($filters['adults'] ?? 1) + (int) ($filters['children'] ?? 0));

        $query = $this->eligibleProperties($filters, $checkIn, $checkOut, $rooms, $guests)
            ->with([
                'locationRecord',
                'roomType',
                'amenities:id,name,icon',
                'publicAccommodationTypes' => fn ($typeQuery) =>
                    $this->applyTypeFilters($typeQuery, $filters, $checkIn, $checkOut, $rooms, $guests)
                        ->with([
                            'ratePlans' => fn ($rateQuery) => $rateQuery
                                ->where('is_active', true)
                                ->where('is_public', true)
                                ->with(['cancellationPolicy', 'paymentPolicy'])
                                ->orderBy('sort_order')
                                ->orderBy('id'),
                        ])
                        ->orderBy('base_rate')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
            ])
            ->withCount('reviews as verified_review_count')
            ->withAvg('reviews as verified_review_score', 'rating')
            ->withCount('favourites as favourites_count')
            ->when(auth()->check(), fn (Builder $q) => $q->withExists([
                'favourites as is_favourite' => fn (Builder $fav) => $fav->where('user_id', auth()->id()),
            ]))
            ->withMin([
                'publicAccommodationTypes as search_min_rate' => fn ($typeQuery) =>
                    $this->applyStaticTypeFilters($typeQuery, $filters, $rooms, $guests),
            ], 'base_rate');

        $this->applySort($query, $filters);

        $perPage = max(6, min((int) config('reserva.search.per_page', 15), 30));
        $paginator = $query->paginate($perPage)->withQueryString();

        $reviewSummaries = $this->reviews->forProperties(
            $paginator->getCollection()->pluck('id')->all()
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(function (Property $property) use (
                $filters,
                $checkIn,
                $checkOut,
                $rooms,
                $reviewSummaries
            ): array {
                $type = $property->publicAccommodationTypes->first();

                if (! $type) {
                    return ['property' => $property, 'unavailable' => true];
                }

                $requestedRatePlanId = isset($filters['rate_plan_id'])
                    ? (int) $filters['rate_plan_id']
                    : null;

                $ratePlan = $type->ratePlans
                    ->first(fn ($plan) => ! $requestedRatePlanId || (int) $plan->getKey() === $requestedRatePlanId)
                    ?: $type->ratePlans->first();

                try {
                    $this->availability->assertRules(
                        $property,
                        $checkIn,
                        $checkOut,
                        (int) ($filters['adults'] ?? 1),
                        (int) ($filters['children'] ?? 0),
                        $rooms,
                        $type,
                        $ratePlan
                    );
                } catch (ValidationException) {
                    return ['property' => $property, 'unavailable' => true];
                }

                $quote = $this->pricing->quote(
                    $property,
                    $checkIn,
                    $checkOut,
                    [],
                    $type,
                    $ratePlan,
                    $rooms
                );

                return [
                    'property' => $property,
                    'accommodation_type' => $type,
                    'rate_plan' => $ratePlan,
                    'remaining' => $this->availability->availableQuantity($type, $checkIn, $checkOut),
                    'quote' => $quote,
                    'reviews' => $reviewSummaries->get($property->getKey(), [
                        'count' => 0,
                        'overall' => null,
                        'categories' => [],
                    ]),
                    'is_favourite' => (bool) ($property->is_favourite ?? false),
                ];
            })->filter(fn (array $result) => empty($result['unavailable']))->values()
        );

        return [
            'results' => $paginator,
            'facets' => $this->facets($filters, $checkIn, $checkOut, $rooms, $guests),
            'map_points' => $paginator->getCollection()
                ->filter(fn (array $result) => $result['property']->latitude !== null && $result['property']->longitude !== null)
                ->map(fn (array $result) => [
                    'id' => $result['property']->getKey(),
                    'name' => $result['property']->name,
                    'slug' => $result['property']->slug,
                    'lat' => (float) $result['property']->latitude,
                    'lng' => (float) $result['property']->longitude,
                    'price' => (float) $result['quote']['total'],
                    'currency' => $result['quote']['currency'],
                ])->values(),
        ];
    }

    public function filterOptions(): array
    {
        return [
            'amenities' => Amenity::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'icon']),
        ];
    }

    private function eligibleProperties(
        array $filters,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $rooms,
        int $guests
    ): Builder {
        $query = Property::query()
            ->where('is_published', true)
            ->where(function (Builder $status): void {
                $status->whereNull('status')
                    ->orWhereNotIn('status', ['inactive', 'unavailable', 'maintenance', 'archived']);
            })
            ->whereHas('publicAccommodationTypes', fn (Builder $typeQuery) =>
                $this->applyTypeFilters($typeQuery, $filters, $checkIn, $checkOut, $rooms, $guests)
            );

        if (! empty($filters['property_id'])) {
            return $query->whereKey((int) $filters['property_id']);
        }

        $query
            ->when($filters['location_id'] ?? null, fn (Builder $q, $value) => $q->where('location_id', (int) $value))
            ->when($filters['room_type_id'] ?? null, fn (Builder $q, $value) => $q->where('room_type_id', (int) $value))
            ->when($filters['property_type'] ?? null, fn (Builder $q, $value) => $q->whereRaw('LOWER(property_type) = ?', [mb_strtolower((string) $value)]))
            ->when($filters['bedrooms'] ?? null, fn (Builder $q, $value) => $q->where('bedrooms', '>=', (int) $value))
            ->when($filters['bathrooms'] ?? null, fn (Builder $q, $value) => $q->where('bathrooms', '>=', (int) $value))
            ->when($filters['neighbourhood'] ?? null, function (Builder $q, $value): void {
                $term = $this->cleanPrefix((string) $value);
                if ($term !== '') {
                    $q->where(function (Builder $inner) use ($term): void {
                        $inner->where('location', 'like', $term.'%')
                            ->orWhere('address_city', 'like', $term.'%')
                            ->orWhereHas('locationRecord', fn (Builder $loc) => $loc
                                ->where('name', 'like', $term.'%')
                                ->orWhere('city', 'like', $term.'%'));
                    });
                }
            });

        if (! empty($filters['destination']) && empty($filters['location_id'])) {
            $term = $this->cleanPrefix((string) $filters['destination']);
            if ($term !== '') {
                $query->where(function (Builder $destination) use ($term): void {
                    $prefix = $term.'%';
                    $destination->where('name', 'like', $prefix)
                        ->orWhere('location', 'like', $prefix)
                        ->orWhere('address_city', 'like', $prefix)
                        ->orWhereHas('locationRecord', fn (Builder $location) => $location
                            ->where('name', 'like', $prefix)
                            ->orWhere('city', 'like', $prefix)
                            ->orWhere('country', 'like', $prefix));
                });
            }
        }

        $amenityIds = collect($filters['amenities'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        foreach ($amenityIds as $amenityId) {
            $query->whereHas('amenities', fn (Builder $amenity) => $amenity->whereKey($amenityId));
        }

        foreach ([
            'wifi' => ['wifi', 'wi-fi'],
            'parking' => ['parking'],
            'pool' => ['pool', 'swimming'],
            'kitchen' => ['kitchen'],
            'air_conditioning' => ['air conditioning', 'air-conditioning', 'ac'],
            'accessibility' => ['accessible', 'wheelchair', 'accessibility'],
        ] as $filter => $terms) {
            if (! empty($filters[$filter])) {
                $query->whereHas('amenities', function (Builder $amenity) use ($terms): void {
                    $amenity->where(function (Builder $nameQuery) use ($terms): void {
                        foreach ($terms as $index => $term) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $nameQuery->{$method}('name', 'like', $term.'%');
                        }
                    });
                });
            }
        }

        if (! empty($filters['guest_rating'])) {
            $query->whereRaw(
                '(SELECT AVG(r.rating) FROM reviews r WHERE r.property_id = properties.id AND r.verified_stay = 1 AND r.status = ?) >= ?',
                ['approved', (float) $filters['guest_rating']]
            );
        }

        if (! empty($filters['breakfast'])) {
            $query->whereHas('publicAccommodationTypes.ratePlans', fn (Builder $rate) => $rate
                ->where('is_active', true)
                ->where('is_public', true)
                ->where(function (Builder $meal): void {
                    $meal->where('meal_plan', 'like', 'Breakfast%')
                        ->orWhere('meal_plan', 'like', '%breakfast%');
                }));
        }

        return $query;
    }

    private function applyTypeFilters(
        Builder|Relation $query,
        array $filters,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $rooms,
        int $guests
    ): Builder|Relation {
        $this->applyStaticTypeFilters($query, $filters, $rooms, $guests);

        $dates = [];
        for ($date = $checkIn; $date->lessThan($checkOut); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        foreach ($dates as $date) {
            $this->applyDateAvailabilityConstraint($query, $date, $rooms);
        }

        $query->whereDoesntHave('property', function (Builder $property) use ($checkIn, $checkOut): void {
            $property->whereHas('maintenancePeriods', fn (Builder $maintenance) => $maintenance
                ->where('blocks_booking', true)
                ->whereDate('starts_on', '<', $checkOut->toDateString())
                ->whereDate('ends_on', '>', $checkIn->toDateString()));
        });

        return $query;
    }

    private function applyStaticTypeFilters(Builder|Relation $query, array $filters, int $rooms, int $guests): Builder|Relation
    {
        $requiredPerUnit = max(1, (int) ceil($guests / max(1, $rooms)));

        $query->where('is_active', true)
            ->where('is_published', true)
            ->where('total_inventory', '>=', $rooms)
            ->where('max_guests', '>=', $requiredPerUnit)
            ->when($filters['accommodation_type_id'] ?? null, fn (Builder $q, $value) => $q->whereKey((int) $value))
            ->when($filters['price_min'] ?? null, fn (Builder $q, $value) => $q->where('base_rate', '>=', (float) $value))
            ->when($filters['price_max'] ?? null, fn (Builder $q, $value) => $q->where('base_rate', '<=', (float) $value))
            ->when($filters['bedrooms'] ?? null, fn (Builder $q, $value) => $q->where('bedrooms', '>=', (int) $value))
            ->when($filters['bathrooms'] ?? null, fn (Builder $q, $value) => $q->where('bathrooms', '>=', (int) $value));

        if (! empty($filters['rate_plan_id'])) {
            $query->whereHas('ratePlans', fn (Builder $rate) => $rate
                ->whereKey((int) $filters['rate_plan_id'])
                ->where('is_active', true)
                ->where('is_public', true));
        }

        if (! empty($filters['free_cancellation'])) {
            $query->whereHas('ratePlans', fn (Builder $rate) => $rate
                ->where('is_active', true)
                ->where('is_public', true)
                ->where('is_refundable', true));
        }

        if (! empty($filters['pay_later'])) {
            $query->whereHas('ratePlans.paymentPolicy', fn (Builder $payment) => $payment
                ->where('is_active', true)
                ->whereIn('payment_type', ['pay_later', 'pay_at_property', 'deposit']));
        }

        return $query;
    }

    private function applyDateAvailabilityConstraint(Builder|Relation $query, string $date, int $rooms): void
    {
        $statuses = config('azari.booking.active_statuses', [
            'hold', 'pending', 'pending_payment', 'approved', 'confirmed',
            'paid', 'check_in', 'checked_in',
        ]);

        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));

        $sql = <<<SQL
(
    COALESCE(
        (
            SELECT CASE
                WHEN idt.stop_sell = 1 THEN 0
                ELSE COALESCE(idt.sellable_inventory, accommodation_types.total_inventory)
                     - COALESCE(idt.maintenance_inventory, 0)
            END
            FROM inventory_dates idt
            WHERE idt.accommodation_type_id = accommodation_types.id
              AND idt.date = ?
            LIMIT 1
        ),
        accommodation_types.total_inventory
    )
    - COALESCE(
        (
            SELECT SUM(COALESCE(b.rooms, 1))
            FROM bookings b
            WHERE b.accommodation_type_id = accommodation_types.id
              AND b.check_in <= ?
              AND b.check_out > ?
              AND b.status IN ($statusPlaceholders)
              AND (
                    b.status NOT IN ('pending', 'pending_payment')
                    OR (b.expires_at IS NOT NULL AND b.expires_at > CURRENT_TIMESTAMP)
                  )
        ),
        0
    )
    - COALESCE(
        (
            SELECT SUM(COALESCE(h.rooms, 1))
            FROM booking_holds h
            WHERE h.accommodation_type_id = accommodation_types.id
              AND h.check_in <= ?
              AND h.check_out > ?
              AND h.expires_at > CURRENT_TIMESTAMP
        ),
        0
    )
) >= ?
SQL;

        $bindings = [
            $date,
            $date,
            $date,
            ...$statuses,
            $date,
            $date,
            $rooms,
        ];

        $query->whereRaw($sql, $bindings);
    }

    private function applySort(Builder $query, array $filters): void
    {
        switch ($filters['sort'] ?? 'recommended') {
            case 'price_asc':
                $query->orderBy('search_min_rate')->orderBy('properties.id');
                break;
            case 'price_desc':
                $query->orderByDesc('search_min_rate')->orderBy('properties.id');
                break;
            case 'rating':
                $query->orderByDesc('verified_review_score')->orderByDesc('verified_review_count')->orderBy('properties.id');
                break;
            case 'popularity':
                $query->orderByDesc('favourites_count')->orderByDesc('verified_review_count')->orderBy('properties.id');
                break;
            case 'distance':
                // Distance ordering becomes exact when the caller supplies coordinates.
                if (isset($filters['latitude'], $filters['longitude'])) {
                    $lat = (float) $filters['latitude'];
                    $lng = (float) $filters['longitude'];

                    if (DB::connection()->getDriverName() !== 'sqlite') {
                        $query->selectRaw(
                            '(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(latitude))))) AS distance_km',
                            [$lat, $lng, $lat]
                        )->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('distance_km')
                            ->orderBy('properties.id');
                        break;
                    }
                }
                $query->orderBy('properties.name')->orderBy('properties.id');
                break;
            default:
                $query->orderByDesc('is_featured')
                    ->orderByDesc('verified_review_score')
                    ->orderByDesc('favourites_count')
                    ->orderBy('sort_order')
                    ->orderBy('properties.id');
        }
    }

    private function facets(
        array $filters,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $rooms,
        int $guests
    ): array {
        $keyFilters = collect($filters)->except(['page', 'sort'])->sortKeys()->all();
        $key = 'reserva:search-facets:'.hash('sha256', json_encode($keyFilters));

        return Cache::remember($key, now()->addSeconds(60), function () use ($filters, $checkIn, $checkOut, $rooms, $guests): array {
            $base = $this->eligibleProperties($filters, $checkIn, $checkOut, $rooms, $guests);

            $propertyTypes = (clone $base)
                ->selectRaw('property_type, COUNT(*) as aggregate')
                ->whereNotNull('property_type')
                ->groupBy('property_type')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'property_type')
                ->map(fn ($count) => (int) $count)
                ->all();

            $locations = (clone $base)
                ->selectRaw('location_id, COUNT(*) as aggregate')
                ->whereNotNull('location_id')
                ->groupBy('location_id')
                ->pluck('aggregate', 'location_id')
                ->map(fn ($count) => (int) $count)
                ->all();

            $eligibleIds = (clone $base)->select('properties.id');

            $amenities = DB::table('amenity_property')
                ->join('amenities', 'amenities.id', '=', 'amenity_property.amenity_id')
                ->whereIn('amenity_property.property_id', $eligibleIds->toBase())
                ->selectRaw('amenities.id, amenities.name, COUNT(DISTINCT amenity_property.property_id) as aggregate')
                ->groupBy('amenities.id', 'amenities.name')
                ->orderByDesc('aggregate')
                ->limit(30)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'count' => (int) $row->aggregate,
                ])->all();

            return [
                'property_types' => $propertyTypes,
                'locations' => $locations,
                'amenities' => $amenities,
            ];
        });
    }

    private function cleanPrefix(string $value): string
    {
        return trim(preg_replace('/[%_\\\\]+/', '', Str::squish($value)) ?? '');
    }
}
