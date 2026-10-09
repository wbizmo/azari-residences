<?php

namespace App\Services\Bookings;

use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Backward-compatible facade. All stay pricing, even legacy callers, uses
 * the same authority as public search, holds and checkout.
 */
class AvailabilityService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $engine,
        private readonly AzariPricingEngine $pricing,
    ) {}

    public function isAvailable(int $propertyId, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $ignoreBookingId = null): bool
    {
        $property = Property::query()->find($propertyId);

        return $property !== null
            && $this->engine->availableForProperty($property, $checkIn, $checkOut, 1, null, $ignoreBookingId);
    }

    public function quote(int $propertyId, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        if ($checkOut->toDateString() <= $checkIn->toDateString()) {
            throw ValidationException::withMessages([
                'check_out' => 'Check-out must follow check-in.',
            ]);
        }
        $property = Property::query()->findOrFail($propertyId);
        $type = $this->engine->resolveAccommodationType($property);
        $ratePlan = $type ? $this->engine->resolveRatePlan($type) : null;

        $available = $this->engine->availableForProperty($property, $checkIn, $checkOut);
        if ($available) {
            try {
                $this->engine->assertRules($property, $checkIn, $checkOut, 1, 0, 1, $type, $ratePlan);
            } catch (ValidationException) {
                $available = false;
            }
        }

        // No independent "USD seasonal quote" with a different tax/fee
        // contract: this matches the authoritative public and checkout quote.
        $quote = $this->pricing->quote($property, $checkIn, $checkOut, [], $type, $ratePlan, 1);

        return [
            ...$quote,
            'available' => $available,
            'minimum_stay' => (int) ($ratePlan?->minimum_stay ?? $type?->minimum_stay ?? $property->minimum_stay ?? 1),
            'maximum_stay' => $ratePlan?->maximum_stay ?? $type?->maximum_stay ?? $property->maximum_stay,
        ];
    }
}
