<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AzariAvailabilityController extends Controller
{
    public function index(
        Request $request,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing
    ): View {
        $filters = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:40'],
            'children' => ['nullable', 'integer', 'min:0', 'max:40'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location' => ['nullable', 'string', 'max:120'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'property_type' => ['nullable', 'string', 'max:80'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'accommodation_type_id' => ['nullable', 'integer', 'exists:accommodation_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'destination_type' => ['nullable', Rule::in(['location', 'property', 'city', 'country'])],
            'destination_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $checkIn = CarbonImmutable::parse($filters['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($filters['check_out'])->startOfDay();
        $adults = (int) $filters['adults'];
        $children = (int) ($filters['children'] ?? 0);
        $rooms = (int) ($filters['rooms'] ?? 1);

        if (($filters['destination_type'] ?? null) === 'location' && ! empty($filters['destination_id'])) {
            $filters['location_id'] = (int) $filters['destination_id'];
        }

        if (($filters['destination_type'] ?? null) === 'property' && ! empty($filters['destination_id'])) {
            $filters['property_id'] = (int) $filters['destination_id'];
        }

        $locations = Location::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $query) => $this->applyPublicInventory($query))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->whereHas('properties', fn (Builder $query) => $this->applyPublicInventory($query))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $filterNotices = [];

        if (! empty($filters['location_id'])
            && ! $locations->contains('id', (int) $filters['location_id'])) {
            $label = Location::query()->find($filters['location_id'])?->name ?? 'Selected location';
            $filterNotices[] = "{$label} currently has no published accommodation, so that filter was removed.";
            unset($filters['location_id']);
        }

        if (! empty($filters['room_type_id'])
            && ! $roomTypes->contains('id', (int) $filters['room_type_id'])) {
            $label = RoomType::query()->find($filters['room_type_id'])?->name ?? 'Selected category';
            $filterNotices[] = "{$label} currently has no published accommodation, so that filter was removed.";
            unset($filters['room_type_id']);
        }

        $request->session()->put('azari_stay_search', [
            'check_in' => $filters['check_in'],
            'check_out' => $filters['check_out'],
            'adults' => $adults,
            'children' => $children,
            'rooms' => $rooms,
            'location_id' => $filters['location_id'] ?? null,
            'room_type_id' => $filters['room_type_id'] ?? null,
            'destination' => $filters['destination'] ?? null,
            'destination_type' => $filters['destination_type'] ?? null,
            'destination_id' => $filters['destination_id'] ?? null,
        ]);

        $query = $this->publicInventoryQuery()
            ->with(['locationRecord', 'roomType', 'amenities']);

        if (! empty($filters['property_id'])) {
            $query->whereKey((int) $filters['property_id']);
        } else {
            $query
                ->when($filters['location_id'] ?? null, fn (Builder $builder, $locationId) =>
                    $builder->where('location_id', $locationId)
                )
                ->when($filters['room_type_id'] ?? null, fn (Builder $builder, $roomTypeId) =>
                    $builder->where('room_type_id', $roomTypeId)
                )
                ->when($filters['property_type'] ?? null, fn (Builder $builder, $type) =>
                    $builder->whereRaw('LOWER(property_type) = ?', [mb_strtolower($type)])
                );

            if (! empty($filters['destination']) && empty($filters['location_id'])) {
                $this->applyDestinationText($query, (string) $filters['destination']);
            }
        }

        // Bounded until the richer paginated/faceted result set lands in the next search phase.
        $properties = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(60)
            ->get();

        $ruleFailures = [];

        $results = $properties->map(function (Property $property) use (
            $availability,
            $pricing,
            $checkIn,
            $checkOut,
            $adults,
            $children,
            $rooms,
            $filters,
            &$ruleFailures
        ): ?array {
            $requestedTypeId = isset($filters['accommodation_type_id'])
                ? (int) $filters['accommodation_type_id']
                : null;

            $type = $availability->resolveAccommodationType($property, $requestedTypeId);

            if ($requestedTypeId && ! $type) {
                return null;
            }

            $requestedRatePlanId = isset($filters['rate_plan_id'])
                ? (int) $filters['rate_plan_id']
                : null;

            $ratePlan = $type
                ? $availability->resolveRatePlan($type, $requestedRatePlanId)
                : null;

            if ($requestedRatePlanId && ! $ratePlan) {
                return null;
            }

            try {
                $availability->assertRules(
                    $property,
                    $checkIn,
                    $checkOut,
                    $adults,
                    $children,
                    $rooms,
                    $type,
                    $ratePlan
                );
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();

                if ($message) {
                    $ruleFailures[] = $message;
                }

                return null;
            }

            if (! $availability->availableForProperty(
                $property,
                $checkIn,
                $checkOut,
                $rooms,
                $type?->getKey()
            )) {
                return null;
            }

            return [
                'property' => $property,
                'accommodation_type' => $type,
                'rate_plan' => $ratePlan,
                'remaining' => $type
                    ? $availability->availableQuantity($type, $checkIn, $checkOut)
                    : 1,
                'quote' => $pricing->quote(
                    $property,
                    $checkIn,
                    $checkOut,
                    [],
                    $type,
                    $ratePlan,
                    $rooms
                ),
            ];
        })->filter()->values();

        $availableLocations = $results
            ->map(fn (array $result) => $result['property']->locationRecord)
            ->filter()
            ->unique('id')
            ->values();

        $emptyState = null;

        if ($results->isEmpty()) {
            if ($properties->isEmpty()) {
                $emptyState = 'inventory';
            } elseif (count($ruleFailures) >= $properties->count()) {
                $emptyState = 'rules';
            } else {
                $emptyState = 'dates';
            }
        }

        $alternatives = $emptyState === 'dates'
            ? $this->findAlternatives(
                $properties,
                $availability,
                $pricing,
                $checkIn,
                $checkOut,
                $adults,
                $children,
                $rooms
            )
            : collect();

        return view('public.bookings.availability', [
            'results' => $results,
            'alternatives' => $alternatives,
            'availableLocations' => $availableLocations,
            'locations' => $locations,
            'roomTypes' => $roomTypes,
            'filters' => $filters,
            'filterNotices' => $filterNotices,
            'emptyState' => $emptyState,
            'ruleFailure' => collect($ruleFailures)->unique()->first(),
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
            'adults' => ['required', 'integer', 'min:1', 'max:40'],
            'children' => ['nullable', 'integer', 'min:0', 'max:40'],
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
            'adults' => ['required', 'integer', 'min:1', 'max:40'],
            'children' => ['nullable', 'integer', 'min:0', 'max:40'],
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

    private function publicInventoryQuery(): Builder
    {
        return $this->applyPublicInventory(Property::query());
    }

    private function applyPublicInventory(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $status): void {
                $status
                    ->whereNull('status')
                    ->orWhereNotIn('status', ['inactive', 'unavailable', 'maintenance', 'archived']);
            });
    }

    private function applyDestinationText(Builder $query, string $rawTerm): void
    {
        $term = trim(preg_replace('/[%_\\\\]+/', '', $rawTerm) ?? '');

        if ($term === '') {
            return;
        }

        $prefix = $term.'%';

        $query->where(function (Builder $destination) use ($prefix): void {
            $destination
                ->where('name', 'like', $prefix)
                ->orWhere('location', 'like', $prefix)
                ->orWhere('address_city', 'like', $prefix)
                ->orWhereHas('locationRecord', function (Builder $location) use ($prefix): void {
                    $location
                        ->where('name', 'like', $prefix)
                        ->orWhere('city', 'like', $prefix)
                        ->orWhere('country', 'like', $prefix);
                });
        });
    }

    private function findAlternatives(
        $properties,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing,
        CarbonImmutable $requestedIn,
        CarbonImmutable $requestedOut,
        int $adults,
        int $children,
        int $rooms
    ) {
        $nights = max(1, $requestedIn->diffInDays($requestedOut));
        $alternatives = collect();

        foreach ($properties->take(12) as $property) {
            $type = $availability->resolveAccommodationType($property);
            $ratePlan = $type ? $availability->resolveRatePlan($type) : null;

            if (! $type) {
                continue;
            }

            $windowStart = $requestedIn->addDay();
            $windowEnd = $requestedIn->addDays(31 + $nights);
            $remaining = $availability->remainingByDate($type, $windowStart, $windowEnd);

            for ($offset = 1; $offset <= 30; $offset++) {
                $checkIn = $requestedIn->addDays($offset);
                $checkOut = $checkIn->addDays($nights);
                $rangeAvailable = true;

                for ($cursor = $checkIn; $cursor->lessThan($checkOut); $cursor = $cursor->addDay()) {
                    if ((int) $remaining->get($cursor->toDateString(), 0) < $rooms) {
                        $rangeAvailable = false;
                        break;
                    }
                }

                if (! $rangeAvailable) {
                    continue;
                }

                try {
                    $availability->assertRules(
                        $property,
                        $checkIn,
                        $checkOut,
                        $adults,
                        $children,
                        $rooms,
                        $type,
                        $ratePlan
                    );
                } catch (ValidationException) {
                    continue;
                }

                $alternatives->push([
                    'property' => $property,
                    'accommodation_type' => $type,
                    'rate_plan' => $ratePlan,
                    'remaining' => $availability->availableQuantity($type, $checkIn, $checkOut),
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'quote' => $pricing->quote(
                        $property,
                        $checkIn,
                        $checkOut,
                        [],
                        $type,
                        $ratePlan,
                        $rooms
                    ),
                ]);

                break;
            }

            if ($alternatives->count() >= 6) {
                break;
            }
        }

        return $alternatives->values();
    }
}
