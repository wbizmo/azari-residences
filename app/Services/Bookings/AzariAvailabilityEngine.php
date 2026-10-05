<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\DailyRate;
use App\Models\InventoryDate;
use App\Models\MaintenancePeriod;
use App\Models\Property;
use App\Models\RatePlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AzariAvailabilityEngine
{
    public function assertRules(
        Property $property,
        CarbonInterface $in,
        CarbonInterface $out,
        int $adults,
        int $children,
        int $rooms = 1,
        ?AccommodationType $accommodationType = null,
        ?RatePlan $ratePlan = null
    ): void {
        $timezone = (string) config('azari.timezone', 'Africa/Lagos');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $checkIn = CarbonImmutable::parse($in->toDateString(), $timezone)->startOfDay();
        $checkOut = CarbonImmutable::parse($out->toDateString(), $timezone)->startOfDay();

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages([
                'check_out' => 'Check-out must be after check-in.',
            ]);
        }

        if ($checkIn->lessThan($today)) {
            throw ValidationException::withMessages([
                'check_in' => 'Check-in cannot be in the past.',
            ]);
        }

        $rooms = max(1, $rooms);

        $sameDayAllowed = $accommodationType?->same_day_booking
            ?? $property->same_day_booking
            ?? config('azari.booking.same_day_booking');

        if (! $sameDayAllowed && $checkIn->isSameDay($today)) {
            throw ValidationException::withMessages([
                'check_in' => 'Same-day booking is unavailable for this accommodation.',
            ]);
        }

        $nights = $checkIn->diffInDays($checkOut);

        $minimumStay = max(
            1,
            (int) ($ratePlan?->minimum_stay
                ?? $accommodationType?->minimum_stay
                ?? $property->minimum_stay
                ?? 1)
        );

        $maximumStay = $ratePlan?->maximum_stay
            ?? $accommodationType?->maximum_stay
            ?? $property->maximum_stay;

        if ($accommodationType && Schema::hasTable('inventory_dates')) {
            $inventoryRules = InventoryDate::query()
                ->where('accommodation_type_id', $accommodationType->getKey())
                ->whereDate('date', '>=', $checkIn->toDateString())
                ->whereDate('date', '<', $checkOut->toDateString())
                ->get(['date', 'minimum_stay', 'maximum_stay']);

            $dateMinimum = $inventoryRules->pluck('minimum_stay')->filter()->max();
            $dateMaximum = $inventoryRules->pluck('maximum_stay')->filter()->min();

            if ($dateMinimum) {
                $minimumStay = max($minimumStay, (int) $dateMinimum);
            }

            if ($dateMaximum) {
                $maximumStay = $maximumStay
                    ? min((int) $maximumStay, (int) $dateMaximum)
                    : (int) $dateMaximum;
            }

            $arrivalClosed = InventoryDate::query()
                ->where('accommodation_type_id', $accommodationType->getKey())
                ->whereDate('date', $checkIn->toDateString())
                ->where('closed_to_arrival', true)
                ->exists();

            if ($arrivalClosed) {
                throw ValidationException::withMessages([
                    'check_in' => 'Arrival is closed for the selected date.',
                ]);
            }

            $departureClosed = InventoryDate::query()
                ->where('accommodation_type_id', $accommodationType->getKey())
                ->whereDate('date', $checkOut->toDateString())
                ->where('closed_to_departure', true)
                ->exists();

            if ($departureClosed) {
                throw ValidationException::withMessages([
                    'check_out' => 'Departure is closed for the selected date.',
                ]);
            }
        }

        if ($accommodationType && Schema::hasTable('daily_rates')) {
            $rateRestrictions = DailyRate::query()
                ->where('accommodation_type_id', $accommodationType->getKey())
                ->whereDate('date', '>=', $checkIn->toDateString())
                ->whereDate('date', '<', $checkOut->toDateString())
                ->where(function (Builder $query) use ($ratePlan): void {
                    $query->whereNull('rate_plan_id');

                    if ($ratePlan) {
                        $query->orWhere('rate_plan_id', $ratePlan->getKey());
                    }
                })
                ->get(['rate_plan_id', 'date', 'minimum_stay', 'maximum_stay', 'stop_sell']);

            $effectiveRateRestrictions = $rateRestrictions
                ->groupBy(fn (DailyRate $row) => $row->date->toDateString())
                ->map(function (Collection $rows) use ($ratePlan): ?DailyRate {
                    if ($ratePlan) {
                        $specific = $rows->first(
                            fn (DailyRate $row) => (int) $row->rate_plan_id === (int) $ratePlan->getKey()
                        );

                        if ($specific) {
                            return $specific;
                        }
                    }

                    return $rows->first(fn (DailyRate $row) => $row->rate_plan_id === null);
                })
                ->filter();

            if ($effectiveRateRestrictions->contains(fn (DailyRate $row) => (bool) $row->stop_sell)) {
                throw ValidationException::withMessages([
                    'rate_plan_id' => 'The selected rate is closed for one or more nights.',
                ]);
            }

            $rateMinimum = $effectiveRateRestrictions->pluck('minimum_stay')->filter()->max();
            $rateMaximum = $effectiveRateRestrictions->pluck('maximum_stay')->filter()->min();

            if ($rateMinimum) {
                $minimumStay = max($minimumStay, (int) $rateMinimum);
            }

            if ($rateMaximum) {
                $maximumStay = $maximumStay
                    ? min((int) $maximumStay, (int) $rateMaximum)
                    : (int) $rateMaximum;
            }
        }

        if ($nights < $minimumStay) {
            throw ValidationException::withMessages([
                'check_out' => "Minimum stay is {$minimumStay} night(s).",
            ]);
        }

        if ($maximumStay && $nights > (int) $maximumStay) {
            throw ValidationException::withMessages([
                'check_out' => "Maximum stay is {$maximumStay} night(s).",
            ]);
        }

        if ($ratePlan) {
            $advanceDays = $today->diffInDays($checkIn, false);

            if ($ratePlan->minimum_advance_days !== null
                && $advanceDays < (int) $ratePlan->minimum_advance_days) {
                throw ValidationException::withMessages([
                    'check_in' => 'This rate requires more advance notice.',
                ]);
            }

            if ($ratePlan->maximum_advance_days !== null
                && $advanceDays > (int) $ratePlan->maximum_advance_days) {
                throw ValidationException::withMessages([
                    'check_in' => 'This rate cannot be booked that far in advance.',
                ]);
            }
        }

        $capacityPerUnit = $accommodationType
            ? $accommodationType->capacityPerUnit()
            : max(1, (int) ($property->max_guests ?? 1));

        $capacity = $capacityPerUnit * $rooms;

        if (($adults + $children) > $capacity) {
            throw ValidationException::withMessages([
                'adults' => "Maximum capacity is {$capacity} guest(s) for {$rooms} unit(s).",
            ]);
        }

        if ($accommodationType) {
            $adultCapacity = max(1, (int) ($accommodationType->adult_capacity ?: $capacityPerUnit)) * $rooms;
            $childCapacity = max(0, (int) $accommodationType->child_capacity) * $rooms;

            if ($adults > $adultCapacity) {
                throw ValidationException::withMessages([
                    'adults' => "Maximum adult capacity is {$adultCapacity}.",
                ]);
            }

            if ($children > $childCapacity && $childCapacity > 0) {
                throw ValidationException::withMessages([
                    'children' => "Maximum child capacity is {$childCapacity}.",
                ]);
            }
        }
    }

    public function resolveAccommodationType(Property $property, ?int $accommodationTypeId = null): ?AccommodationType
    {
        if (! Schema::hasTable('accommodation_types')) {
            return null;
        }

        return AccommodationType::query()
            ->where('property_id', $property->getKey())
            ->where('is_active', true)
            ->where('is_published', true)
            ->when($accommodationTypeId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public function resolveRatePlan(
        AccommodationType $accommodationType,
        ?int $ratePlanId = null
    ): ?RatePlan {
        if (! Schema::hasTable('rate_plans')) {
            return null;
        }

        return RatePlan::query()
            ->with(['cancellationPolicy', 'paymentPolicy'])
            ->where('accommodation_type_id', $accommodationType->getKey())
            ->where('is_active', true)
            ->where('is_public', true)
            ->when($ratePlanId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public function availableForProperty(
        Property $property,
        CarbonInterface $in,
        CarbonInterface $out,
        int $quantity = 1,
        ?int $accommodationTypeId = null,
        ?int $ignoreBooking = null,
        ?string $ignoreHold = null
    ): bool {
        $type = $this->resolveAccommodationType($property, $accommodationTypeId);

        if (! $type) {
            return $this->legacyAvailable(
                $property->getKey(),
                $in,
                $out,
                $ignoreBooking,
                $ignoreHold
            );
        }

        return $this->availableQuantity(
            $type,
            $in,
            $out,
            $ignoreBooking,
            $ignoreHold
        ) >= max(1, $quantity);
    }

    public function available(
        int $propertyId,
        CarbonInterface $in,
        CarbonInterface $out,
        ?int $ignoreBooking = null,
        ?string $ignoreHold = null
    ): bool {
        $property = Property::query()->find($propertyId);

        if (! $property) {
            return false;
        }

        return $this->availableForProperty(
            $property,
            $in,
            $out,
            1,
            null,
            $ignoreBooking,
            $ignoreHold
        );
    }

    public function availableQuantity(
        AccommodationType $accommodationType,
        CarbonInterface $in,
        CarbonInterface $out,
        ?int $ignoreBooking = null,
        ?string $ignoreHold = null
    ): int {
        $remaining = $this->remainingByDate(
            $accommodationType,
            $in,
            $out,
            $ignoreBooking,
            $ignoreHold
        );

        if ($remaining->isEmpty()) {
            return 0;
        }

        return max(0, (int) $remaining->min());
    }

    public function remainingByDate(
        AccommodationType $accommodationType,
        CarbonInterface $in,
        CarbonInterface $out,
        ?int $ignoreBooking = null,
        ?string $ignoreHold = null
    ): Collection {
        $start = CarbonImmutable::parse($in->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($out->toDateString())->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            return collect();
        }

        $dates = collect();
        for ($cursor = $start; $cursor->lessThan($end); $cursor = $cursor->addDay()) {
            $dates->push($cursor->toDateString());
        }

        if ($this->propertyMaintenanceBlocks(
            $accommodationType->property_id,
            $start,
            $end
        )) {
            return $dates->mapWithKeys(fn (string $date) => [$date => 0]);
        }

        $inventory = Schema::hasTable('inventory_dates')
            ? InventoryDate::query()
                ->where('accommodation_type_id', $accommodationType->getKey())
                ->whereIn('date', $dates->all())
                ->get()
                ->keyBy(fn (InventoryDate $row) => $row->date->toDateString())
            : collect();

        $events = [];

        $applyEvent = static function (string $date, int $delta) use (&$events): void {
            $events[$date] = ($events[$date] ?? 0) + $delta;
        };

        $isPrimaryType = $this->isPrimaryAccommodationType($accommodationType);

        $blockingBookings = Booking::query()
            ->where('property_id', $accommodationType->property_id)
            ->where(function (Builder $query) use ($accommodationType, $isPrimaryType): void {
                $query->where('accommodation_type_id', $accommodationType->getKey());

                if ($isPrimaryType) {
                    $query->orWhereNull('accommodation_type_id');
                }
            })
            ->whereIn('status', config('azari.booking.active_statuses', [
                'hold', 'pending', 'pending_payment', 'approved', 'confirmed',
                'paid', 'check_in', 'checked_in',
            ]))
            ->where(function (Builder $query): void {
                $query->whereNotIn('status', ['pending', 'pending_payment'])
                    ->orWhere(function (Builder $pending): void {
                        $pending->whereIn('status', ['pending', 'pending_payment'])
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            })
            ->when($ignoreBooking, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['check_in', 'check_out', 'rooms']);

        foreach ($blockingBookings as $booking) {
            $this->addOccupancyEvents(
                $events,
                $start,
                $end,
                $booking->check_in,
                $booking->check_out,
                max(1, (int) $booking->rooms)
            );
        }

        $blockingHolds = BookingHold::query()
            ->active()
            ->where('property_id', $accommodationType->property_id)
            ->where(function (Builder $query) use ($accommodationType, $isPrimaryType): void {
                $query->where('accommodation_type_id', $accommodationType->getKey());

                if ($isPrimaryType) {
                    $query->orWhereNull('accommodation_type_id');
                }
            })
            ->when($ignoreHold, fn (Builder $query, string $token) => $query->where('token', '!=', $token))
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['check_in', 'check_out', 'rooms']);

        foreach ($blockingHolds as $hold) {
            $this->addOccupancyEvents(
                $events,
                $start,
                $end,
                $hold->check_in,
                $hold->check_out,
                max(1, (int) $hold->rooms)
            );
        }

        $remaining = collect();
        $occupied = 0;
        $baseInventory = max(0, (int) $accommodationType->total_inventory);

        foreach ($dates as $date) {
            $occupied += (int) ($events[$date] ?? 0);
            $row = $inventory->get($date);

            $sellable = $row?->sellable_inventory;
            $sellable = $sellable === null ? $baseInventory : max(0, (int) $sellable);
            $maintenance = max(0, (int) ($row?->maintenance_inventory ?? 0));

            if ($row?->stop_sell) {
                $sellable = 0;
            }

            $remaining->put($date, max(0, $sellable - $maintenance - $occupied));
        }

        return $remaining;
    }

    private function addOccupancyEvents(
        array &$events,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        CarbonInterface|string $rawStart,
        CarbonInterface|string $rawEnd,
        int $quantity
    ): void {
        $start = CarbonImmutable::parse($rawStart)->startOfDay();
        $end = CarbonImmutable::parse($rawEnd)->startOfDay();

        if ($start->lessThan($windowStart)) {
            $start = $windowStart;
        }

        if ($end->greaterThan($windowEnd)) {
            $end = $windowEnd;
        }

        if ($end->lessThanOrEqualTo($start)) {
            return;
        }

        $startKey = $start->toDateString();
        $endKey = $end->toDateString();

        $events[$startKey] = ($events[$startKey] ?? 0) + $quantity;
        $events[$endKey] = ($events[$endKey] ?? 0) - $quantity;
    }

    private function isPrimaryAccommodationType(AccommodationType $type): bool
    {
        if (! Schema::hasTable('accommodation_types')) {
            return true;
        }

        $firstId = AccommodationType::query()
            ->where('property_id', $type->property_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return (int) $firstId === (int) $type->getKey();
    }

    private function propertyMaintenanceBlocks(
        int $propertyId,
        CarbonInterface $in,
        CarbonInterface $out
    ): bool {
        if (! Schema::hasTable('maintenance_periods')) {
            return false;
        }

        return MaintenancePeriod::query()
            ->where('property_id', $propertyId)
            ->where('blocks_booking', true)
            ->whereDate('starts_on', '<', $out->toDateString())
            ->whereDate('ends_on', '>', $in->toDateString())
            ->exists();
    }

    public function calendar(
        int $propertyId,
        CarbonInterface $from,
        int $days = 90
    ): array {
        $days = max(1, min($days, 366));
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = $start->addDays($days);
        $property = Property::query()->find($propertyId);

        if ($property && ($type = $this->resolveAccommodationType($property))) {
            $remaining = $this->remainingByDate($type, $start, $end);

            return $remaining->map(
                fn (int $quantity, string $date) => [
                    'date' => CarbonImmutable::parse($date),
                    'available' => $quantity > 0,
                    'remaining' => $quantity,
                    'state' => $quantity > 0 ? 'available' : 'unavailable',
                ]
            )->values()->all();
        }

        return $this->legacyCalendar($propertyId, $start, $days);
    }

    public function hold(
        Property $property,
        CarbonInterface $in,
        CarbonInterface $out,
        int $adults,
        int $children,
        int $rooms,
        ?int $userId,
        ?int $accommodationTypeId = null,
        ?int $ratePlanId = null
    ): BookingHold {
        $rooms = max(1, $rooms);
        $accommodationType = $this->resolveAccommodationType($property, $accommodationTypeId);
        $ratePlan = $accommodationType
            ? $this->resolveRatePlan($accommodationType, $ratePlanId)
            : null;

        if ($accommodationTypeId && ! $accommodationType) {
            throw ValidationException::withMessages([
                'accommodation_type_id' => 'The selected accommodation type is unavailable.',
            ]);
        }

        if ($ratePlanId && ! $ratePlan) {
            throw ValidationException::withMessages([
                'rate_plan_id' => 'The selected rate plan is unavailable.',
            ]);
        }

        $this->assertRules(
            $property,
            $in,
            $out,
            $adults,
            $children,
            $rooms,
            $accommodationType,
            $ratePlan
        );

        return DB::transaction(function () use (
            $property,
            $in,
            $out,
            $adults,
            $children,
            $rooms,
            $userId,
            $accommodationType,
            $ratePlan
        ): BookingHold {
            Property::query()
                ->whereKey($property->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            BookingHold::query()
                ->where('property_id', $property->getKey())
                ->where('expires_at', '<=', now())
                ->delete();

            if ($accommodationType) {
                $lockedType = AccommodationType::query()
                    ->whereKey($accommodationType->getKey())
                    ->where('property_id', $property->getKey())
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->when(
                        DB::connection()->getDriverName() !== 'sqlite',
                        fn (Builder $query) => $query->lockForUpdate()
                    )
                    ->firstOrFail();

                $this->lockInventoryRange($lockedType, $in, $out);

                if ($this->availableQuantity($lockedType, $in, $out) < $rooms) {
                    throw ValidationException::withMessages([
                        'rooms' => 'The requested quantity is no longer available.',
                    ]);
                }
            } elseif (! $this->legacyAvailable($property->getKey(), $in, $out)) {
                throw ValidationException::withMessages([
                    'property_id' => 'Accommodation is no longer available.',
                ]);
            }

            return BookingHold::query()->create([
                'property_id' => $property->getKey(),
                'accommodation_type_id' => $accommodationType?->getKey(),
                'rate_plan_id' => $ratePlan?->getKey(),
                'user_id' => $userId,
                'check_in' => $in,
                'check_out' => $out,
                'adults' => $adults,
                'children' => $children,
                'rooms' => $rooms,
                'expires_at' => now()->addMinutes(
                    max(1, (int) config('azari.booking.hold_minutes', 15))
                ),
            ]);
        }, 5);
    }

    public function lockInventoryRange(
        AccommodationType $accommodationType,
        CarbonInterface $in,
        CarbonInterface $out
    ): void {
        if (! Schema::hasTable('inventory_dates')) {
            return;
        }

        $start = CarbonImmutable::parse($in->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($out->toDateString())->startOfDay();
        $records = [];

        for ($cursor = $start; $cursor->lessThan($end); $cursor = $cursor->addDay()) {
            $records[] = [
                'accommodation_type_id' => $accommodationType->getKey(),
                'date' => $cursor->toDateString(),
                'sellable_inventory' => null,
                'maintenance_inventory' => 0,
                'stop_sell' => false,
                'closed_to_arrival' => false,
                'closed_to_departure' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($records !== []) {
            DB::table('inventory_dates')->insertOrIgnore($records);

            InventoryDate::query()
                ->where('accommodation_type_id', $accommodationType->getKey())
                ->whereIn('date', array_column($records, 'date'))
                ->orderBy('date')
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->get();
        }
    }

    private function legacyAvailable(
        int $propertyId,
        CarbonInterface $in,
        CarbonInterface $out,
        ?int $ignoreBooking = null,
        ?string $ignoreHold = null
    ): bool {
        $bookingConflict = Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', config('azari.booking.active_statuses', [
                'hold', 'pending', 'pending_payment', 'approved', 'confirmed',
                'paid', 'check_in', 'checked_in',
            ]))
            ->where(function (Builder $query): void {
                $query->whereNotIn('status', ['pending', 'pending_payment'])
                    ->orWhere(function (Builder $pending): void {
                        $pending->whereIn('status', ['pending', 'pending_payment'])
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            })
            ->when($ignoreBooking, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->whereDate('check_in', '<', $out->toDateString())
            ->whereDate('check_out', '>', $in->toDateString())
            ->exists();

        if ($bookingConflict) {
            return false;
        }

        $holdConflict = BookingHold::query()
            ->active()
            ->where('property_id', $propertyId)
            ->when($ignoreHold, fn (Builder $query, string $token) => $query->where('token', '!=', $token))
            ->whereDate('check_in', '<', $out->toDateString())
            ->whereDate('check_out', '>', $in->toDateString())
            ->exists();

        return ! $holdConflict && ! $this->propertyMaintenanceBlocks($propertyId, $in, $out);
    }

    private function legacyCalendar(
        int $propertyId,
        CarbonImmutable $start,
        int $days
    ): array {
        $end = $start->addDays($days);
        $blocked = [];

        $markBlocked = static function ($fromDate, $toDate) use (&$blocked, $start, $end): void {
            $cursor = CarbonImmutable::parse($fromDate)->startOfDay();
            $limit = CarbonImmutable::parse($toDate)->startOfDay();

            if ($cursor->lessThan($start)) {
                $cursor = $start;
            }

            if ($limit->greaterThan($end)) {
                $limit = $end;
            }

            while ($cursor->lessThan($limit)) {
                $blocked[$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        };

        Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', config('azari.booking.active_statuses', [
                'hold', 'pending', 'pending_payment', 'approved', 'confirmed',
                'paid', 'check_in', 'checked_in',
            ]))
            ->where(function (Builder $query): void {
                $query->whereNotIn('status', ['pending', 'pending_payment'])
                    ->orWhere(function (Builder $pending): void {
                        $pending->whereIn('status', ['pending', 'pending_payment'])
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            })
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['check_in', 'check_out'])
            ->each(fn (Booking $booking) => $markBlocked($booking->check_in, $booking->check_out));

        BookingHold::query()
            ->active()
            ->where('property_id', $propertyId)
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['check_in', 'check_out'])
            ->each(fn (BookingHold $hold) => $markBlocked($hold->check_in, $hold->check_out));

        if (Schema::hasTable('maintenance_periods')) {
            MaintenancePeriod::query()
                ->where('property_id', $propertyId)
                ->where('blocks_booking', true)
                ->whereDate('starts_on', '<', $end->toDateString())
                ->whereDate('ends_on', '>', $start->toDateString())
                ->get(['starts_on', 'ends_on'])
                ->each(fn (MaintenancePeriod $period) => $markBlocked($period->starts_on, $period->ends_on));
        }

        $calendar = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset);
            $isAvailable = ! isset($blocked[$date->toDateString()]);
            $calendar[] = [
                'date' => $date,
                'available' => $isAvailable,
                'remaining' => $isAvailable ? 1 : 0,
                'state' => $isAvailable ? 'available' : 'unavailable',
            ];
        }

        return $calendar;
    }
}
