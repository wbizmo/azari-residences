<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\MarketplaceSearchRequest;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Search\MarketplaceSearchService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AzariAvailabilityController extends Controller
{
    public function index(
        MarketplaceSearchRequest $request,
        MarketplaceSearchService $marketplace,
        AnalyticsTracker $analytics
    ): View {
        $filters = $request->validated();

        if (($filters['destination_type'] ?? null) === 'location' && ! empty($filters['destination_id'])) {
            $filters['location_id'] = (int) $filters['destination_id'];
        }

        if (($filters['destination_type'] ?? null) === 'property' && ! empty($filters['destination_id'])) {
            $filters['property_id'] = (int) $filters['destination_id'];
        }

        $filters['children'] = (int) ($filters['children'] ?? 0);
        $filters['rooms'] = (int) ($filters['rooms'] ?? 1);
        $filters['sort'] = $filters['sort'] ?? 'recommended';

        $request->session()->put('azari_stay_search', collect($filters)
            ->only([
                'check_in', 'check_out', 'adults', 'children', 'rooms',
                'location_id', 'room_type_id', 'destination',
                'destination_type', 'destination_id',
            ])
            ->all());

        $search = $marketplace->search($filters);
        $results = $search['results'];

        $locations = Location::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $query) => $query->where('is_published', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $query) => $query->where('is_published', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $availableLocations = $results->getCollection()
            ->map(fn (array $result) => $result['property']->locationRecord)
            ->filter()
            ->unique('id')
            ->values();

        $fingerprint = hash('sha256', json_encode(
            collect($filters)->except('page')->sortKeys()->all()
        ));
        $sessionHash = hash('sha256', $request->session()->getId());

        $analytics->track('search_submitted', [
            'user_id' => $request->user()?->getKey(),
            'source' => 'availability_results',
            'payload' => [
                'rooms' => (int) $filters['rooms'],
                'adults' => (int) $filters['adults'],
                'children' => (int) $filters['children'],
                'sort' => $filters['sort'],
                'has_destination' => filled($filters['destination'] ?? null),
                'result_total' => $results->total(),
            ],
        ], hash('sha256', 'search|'.$sessionHash.'|'.$fingerprint));

        $analytics->track('results_viewed', [
            'user_id' => $request->user()?->getKey(),
            'source' => 'availability_results',
            'payload' => [
                'page' => $results->currentPage(),
                'result_total' => $results->total(),
            ],
        ], hash('sha256', 'results|'.$sessionHash.'|'.$fingerprint.'|'.$results->currentPage()));

        return view('public.bookings.availability', [
            'results' => $results,
            'alternatives' => collect(),
            'availableLocations' => $availableLocations,
            'locations' => $locations,
            'roomTypes' => $roomTypes,
            'amenities' => $marketplace->filterOptions()['amenities'],
            'facets' => $search['facets'],
            'mapPoints' => $search['map_points'],
            'filters' => $filters,
            'filterNotices' => [],
            'emptyState' => $results->total() === 0 ? 'dates' : null,
            'ruleFailure' => null,
        ]);
    }

    public function hold(
        Request $request,
        Property $property,
        AzariAvailabilityEngine $availability
    ): RedirectResponse {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'accommodation_type_id' => ['nullable', 'integer', 'exists:accommodation_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
        ]);

        abort_unless(
            $property->is_published
            && ! in_array($property->status, ['inactive', 'unavailable', 'maintenance', 'archived'], true),
            404
        );

        $remembered = (array) $request->session()->get('azari_stay_search', []);

        $request->session()->put('azari_stay_search', array_merge($remembered, [
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'rooms' => (int) ($data['rooms'] ?? 1),
        ]));

        $hold = $availability->hold(
            $property,
            CarbonImmutable::parse($data['check_in']),
            CarbonImmutable::parse($data['check_out']),
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            (int) ($data['rooms'] ?? 1),
            $request->user()?->getKey(),
            isset($data['accommodation_type_id']) ? (int) $data['accommodation_type_id'] : null,
            isset($data['rate_plan_id']) ? (int) $data['rate_plan_id'] : null
        );

        return redirect()->route('azari.booking.checkout', $hold->token);
    }

    public function quote(
        Request $request,
        Property $property,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing
    ) {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'accommodation_type_id' => ['nullable', 'integer', 'exists:accommodation_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'add_ons' => ['nullable', 'array'],
        ]);

        $checkIn = CarbonImmutable::parse($data['check_in']);
        $checkOut = CarbonImmutable::parse($data['check_out']);
        $rooms = (int) ($data['rooms'] ?? 1);

        $type = $availability->resolveAccommodationType(
            $property,
            isset($data['accommodation_type_id']) ? (int) $data['accommodation_type_id'] : null
        );

        $ratePlan = $type
            ? $availability->resolveRatePlan(
                $type,
                isset($data['rate_plan_id']) ? (int) $data['rate_plan_id'] : null
            )
            : null;

        $availability->assertRules(
            $property,
            $checkIn,
            $checkOut,
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            $rooms,
            $type,
            $ratePlan
        );

        return response()->json([
            'available' => $availability->availableForProperty(
                $property,
                $checkIn,
                $checkOut,
                $rooms,
                $type?->getKey()
            ),
            'remaining' => $type
                ? $availability->availableQuantity($type, $checkIn, $checkOut)
                : null,
            'quote' => $pricing->quote(
                $property,
                $checkIn,
                $checkOut,
                $data['add_ons'] ?? [],
                $type,
                $ratePlan,
                $rooms
            ),
        ]);
    }
}
