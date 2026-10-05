<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Search\DestinationSearchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AvailabilitySearchController extends Controller
{
    public function __invoke(
        Request $request,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing,
        DestinationSearchService $destinations
    ): View {
        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'property_type' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'destination_type' => ['nullable', Rule::in(['location', 'property', 'city', 'country'])],
            'destination_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (($validated['destination_type'] ?? null) === 'location' && ! empty($validated['destination_id'])) {
            $validated['location_id'] = (int) $validated['destination_id'];
        }

        if (($validated['destination_type'] ?? null) === 'property' && ! empty($validated['destination_id'])) {
            $validated['property_id'] = (int) $validated['destination_id'];
        }

        $typedDestination = $destinations->normalize(
            (string) ($validated['destination'] ?? $validated['location'] ?? '')
        );

        if (empty($validated['property_id']) && empty($validated['location_id']) && $typedDestination !== '') {
            $propertyId = Property::query()
                ->where('is_published', true)
                ->where('name', $typedDestination)
                ->value('id');

            if ($propertyId) {
                $validated['property_id'] = (int) $propertyId;
                $validated['destination_type'] = 'property';
                $validated['destination_id'] = (int) $propertyId;
            } else {
                $locationId = Location::query()
                    ->where('is_active', true)
                    ->where(function ($query) use ($typedDestination): void {
                        $query
                            ->where('name', $typedDestination)
                            ->orWhere('city', $typedDestination);
                    })
                    ->value('id');

                if ($locationId) {
                    $validated['location_id'] = (int) $locationId;
                    $validated['destination_type'] = 'location';
                    $validated['destination_id'] = (int) $locationId;
                }
            }
        }

        if ($typedDestination !== '') {
            $validated['destination'] = $typedDestination;
        }

        $request->merge([
            ...$validated,
            'children' => (int) ($validated['children'] ?? 0),
            'rooms' => (int) ($validated['rooms'] ?? 1),
        ]);

        return app(AzariAvailabilityController::class)->index(
            $request,
            $availability,
            $pricing
        );
    }
}
