<?php

namespace App\Services\Search;

use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PropertyStayPriceCalendar
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
    ) {}

    /**
     * At most 31 authoritative full-stay quotes for a single published
     * property/room/rate. Pre-load per-night capacity once; no fabricated
     * "from" prices or date-only rates that omit fees/taxes.
     */
    public function month(Property $property, array $options): array
    {
        $timezone = LocalDate::propertyTimezone($property);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $month = CarbonImmutable::createFromFormat('!Y-m-d', $options['month'].'-01', $timezone);
        if (! $month || $month->startOfMonth()->diffInMonths($today->startOfMonth(), true) > 13
            || $month->startOfMonth()->lessThan($today->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'Choose a month within the next 13 months.']);
        }

        $nights = (int) $options['nights'];
        $rooms = (int) ($options['rooms'] ?? 1);
        $adults = (int) ($options['adults'] ?? 1);
        $children = (int) ($options['children'] ?? 0);
        $typeId = isset($options['accommodation_type_id']) ? (int) $options['accommodation_type_id'] : null;
        $planId = isset($options['rate_plan_id']) ? (int) $options['rate_plan_id'] : null;

        $type = $this->availability->resolveAccommodationType($property, $typeId);
        if ($typeId && (! $type || (int) $type->property_id !== (int) $property->id)) {
            throw ValidationException::withMessages(['accommodation_type_id' => 'This accommodation is unavailable.']);
        }
        $ratePlan = $type
            ? $this->availability->resolveRatePlan($type, $planId)
            : null;
        if ($planId && (! $ratePlan || (int) $ratePlan->accommodation_type_id !== (int) $type?->id)) {
            throw ValidationException::withMessages(['rate_plan_id' => 'This rate is unavailable.']);
        }

        $start = $month->startOfMonth();
        $afterLastCheckout = $start->addMonth()->addDays($nights);
        $remaining = $type
            ? $this->availability->remainingByDate($type, $start, $afterLastCheckout)
            : null;
        $days = [];
        for ($in = $start; $in->lt($start->addMonth()); $in = $in->addDay()) {
            $out = $in->addDays($nights);
            $entry = [
                'date' => $in->toDateString(),
                'check_out' => $out->toDateString(),
                'available' => false,
                'total' => null,
                'currency' => null,
                'url' => null,
            ];

            if ($in->lt($today)) {
                $days[] = $entry;
                continue;
            }

            if ($remaining !== null) {
                $capacityOk = true;
                for ($night = $in; $night->lt($out); $night = $night->addDay()) {
                    if ((int) $remaining->get($night->toDateString(), 0) < $rooms) {
                        $capacityOk = false;
                        break;
                    }
                }
                if (! $capacityOk) {
                    $days[] = $entry;
                    continue;
                }
            }

            try {
                $this->availability->assertRules(
                    $property, $in, $out, $adults, $children, $rooms, $type, $ratePlan
                );
                if (! $this->availability->availableForProperty(
                    $property, $in, $out, $rooms, $type?->getKey()
                )) {
                    $days[] = $entry;
                    continue;
                }

                $quote = $this->pricing->quote(
                    $property, $in, $out, [], $type, $ratePlan, $rooms
                );
                $entry['available'] = true;
                $entry['total'] = $quote['total'];
                $entry['currency'] = $quote['currency'];
                $entry['url'] = route('availability.results', array_filter([
                    'property_id' => $property->id,
                    'check_in' => $entry['date'],
                    'check_out' => $entry['check_out'],
                    'adults' => $adults,
                    'children' => $children,
                    'rooms' => $rooms,
                    'accommodation_type_id' => $type?->id,
                    'rate_plan_id' => $ratePlan?->id,
                ], static fn ($value) => $value !== null));
            } catch (ValidationException) {
                // Sold out, closed dates or policy failure remain unavailable,
                // never interactive with an invented quote.
            }

            $days[] = $entry;
        }

        return [
            'month' => $start->format('Y-m'),
            'timezone' => $timezone,
            'nights' => $nights,
            'type' => $type,
            'ratePlan' => $ratePlan,
            'days' => $days,
        ];
    }
}
