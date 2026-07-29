<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        $checkIn = CarbonImmutable::parse($filters['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($filters['check_out'])->startOfDay();
        $adults = (int) $filters['adults'];
        $children = (int) ($filters['children'] ?? 0);
        $rooms = (int) ($filters['rooms'] ?? 1);

        $properties = Property::query()
            ->with(['locationRecord', 'roomType', 'amenities'])
            ->where('is_published', true)
            ->where('status', '!=', 'inactive')
            ->when($filters['property_id'] ?? null, fn ($query, $propertyId) => $query->whereKey($propertyId))
            ->when($filters['location_id'] ?? null, fn ($query, $locationId) => $query->where('location_id', $locationId))
            ->when($filters['room_type_id'] ?? null, fn ($query, $roomTypeId) => $query->where('room_type_id', $roomTypeId))
            ->when($filters['property_type'] ?? null, fn ($query, $type) => $query->whereRaw('LOWER(property_type) = ?', [mb_strtolower($type)]))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $results = $properties
            ->filter(function (Property $property) use (
                $availability,
                $checkIn,
                $checkOut,
                $adults,
                $children,
                $rooms
            ): bool {
                try {
                    $availability->assertRules(
                        $property,
                        $checkIn,
                        $checkOut,
                        $adults,
                        $children,
                        $rooms
                    );
                } catch (ValidationException) {
                    return false;
                }

                return $availability->available($property->getKey(), $checkIn, $checkOut);
            })
            ->map(fn (Property $property) => [
                'property' => $property,
                'quote' => $pricing->quote($property, $checkIn, $checkOut),
            ])
            ->values();

        $availableLocations = $results
            ->map(fn (array $result) => $result['property']->locationRecord)
            ->filter()
            ->unique('id')
            ->values();

        $alternatives = collect();
        if ($results->isEmpty()) {
            $alternatives = $this->findAlternatives(
                $properties,
                $availability,
                $pricing,
                $checkIn,
                $checkOut,
                $adults,
                $children,
                $rooms
            );
        }

        return view('public.bookings.availability', [
            'results' => $results,
            'alternatives' => $alternatives,
            'availableLocations' => $availableLocations,
            'locations' => Location::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
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
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless($property->is_published && $property->status !== 'inactive', 404);

        $hold = $availability->hold(
            $property,
            CarbonImmutable::parse($data['check_in']),
            CarbonImmutable::parse($data['check_out']),
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            (int) ($data['rooms'] ?? 1),
            $request->user()?->getKey()
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
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['nullable', 'integer', 'min:1'],
            'add_ons' => ['nullable', 'array'],
        ]);

        $checkIn = CarbonImmutable::parse($data['check_in']);
        $checkOut = CarbonImmutable::parse($data['check_out']);

        $availability->assertRules(
            $property,
            $checkIn,
            $checkOut,
            (int) $data['adults'],
            (int) ($data['children'] ?? 0),
            (int) ($data['rooms'] ?? 1)
        );

        return response()->json([
            'available' => $availability->available($property->getKey(), $checkIn, $checkOut),
            'quote' => $pricing->quote($property, $checkIn, $checkOut, $data['add_ons'] ?? []),
        ]);
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

        foreach ($properties as $property) {
            for ($offset = 1; $offset <= 30; $offset++) {
                $checkIn = $requestedIn->addDays($offset);
                $checkOut = $checkIn->addDays($nights);

                try {
                    $availability->assertRules(
                        $property,
                        $checkIn,
                        $checkOut,
                        $adults,
                        $children,
                        $rooms
                    );
                } catch (ValidationException) {
                    continue;
                }

                if ($availability->available($property->getKey(), $checkIn, $checkOut)) {
                    $alternatives->push([
                        'property' => $property,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'quote' => $pricing->quote($property, $checkIn, $checkOut),
                    ]);
                    break;
                }
            }
        }

        return $alternatives->take(6)->values();
    }
}
