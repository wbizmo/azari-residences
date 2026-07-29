<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilitySearchController extends Controller
{
    public function __invoke(
        Request $request,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing
    ): View {
        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'property_type' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        if (empty($validated['location_id']) && ! empty($validated['location'])) {
            $location = mb_strtolower($validated['location']);

            $locationId = Location::query()
                ->whereRaw('LOWER(name) = ?', [$location])
                ->orWhereRaw('LOWER(city) = ?', [$location])
                ->value('id');

            if ($locationId !== null) {
                $validated['location_id'] = $locationId;
            }
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
