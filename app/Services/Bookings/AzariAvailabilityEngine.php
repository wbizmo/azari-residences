<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\MaintenancePeriod;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AzariAvailabilityEngine
{
    public function assertRules(
        Property $property,
        CarbonInterface $in,
        CarbonInterface $out,
        int $adults,
        int $children,
        int $rooms = 1
    ): void {
        $timezone = (string) config(
            'azari.timezone',
            'Africa/Lagos'
        );

        $today = CarbonImmutable::now($timezone)
            ->startOfDay();

        $checkIn = CarbonImmutable::parse(
            $in->toDateString(),
            $timezone
        )->startOfDay();

        $checkOut = CarbonImmutable::parse(
            $out->toDateString(),
            $timezone
        )->startOfDay();

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

        if (
            ! (
                $property->same_day_booking
                ?? config('azari.booking.same_day_booking')
            )
            && $checkIn->isSameDay($today)
        ) {
            throw ValidationException::withMessages([
                'check_in' =>
                    'Same-day booking is unavailable for this residence.',
            ]);
        }

        $nights = $checkIn->diffInDays($checkOut);

        $minimumStay = max(
            1,
            (int) ($property->minimum_stay ?? 1)
        );

        $maximumStay = $property->maximum_stay
            ? (int) $property->maximum_stay
            : null;

        if ($nights < $minimumStay) {
            throw ValidationException::withMessages([
                'check_out' =>
                    "Minimum stay is {$minimumStay} night(s).",
            ]);
        }

        if ($maximumStay && $nights > $maximumStay) {
            throw ValidationException::withMessages([
                'check_out' =>
                    "Maximum stay is {$maximumStay} night(s).",
            ]);
        }

        $capacity = max(
            1,
            (int) ($property->max_guests ?? 1)
        ) * max(1, $rooms);

        if (($adults + $children) > $capacity) {
            throw ValidationException::withMessages([
                'adults' =>
                    "Maximum capacity is {$capacity} guest(s).",
            ]);
        }
    }

    public function available(
        int $propertyId,
        CarbonInterface $in,
        CarbonInterface $out,
        ?int $ignoreBooking = null,
        ?string $ignoreHold = null
    ): bool {
        $bookingConflict = Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn(
                'status',
                config(
                    'azari.booking.active_statuses',
                    [
                        'hold',
                        'pending',
                        'pending_payment',
                        'approved',
                        'confirmed',
                        'paid',
                        'check_in',
                        'checked_in',
                    ]
                )
            )
            ->where(
                function (Builder $query): void {
                    $query
                        ->whereNotIn(
                            'status',
                            [
                                'pending',
                                'pending_payment',
                            ]
                        )
                        ->orWhere(
                            function (Builder $pending): void {
                                $pending
                                    ->whereIn(
                                        'status',
                                        [
                                            'pending',
                                            'pending_payment',
                                        ]
                                    )
                                    ->whereNotNull('expires_at')
                                    ->where(
                                        'expires_at',
                                        '>',
                                        now()
                                    );
                            }
                        );
                }
            )
            ->when(
                $ignoreBooking,
                fn (Builder $query) =>
                    $query->whereKeyNot(
                        $ignoreBooking
                    )
            )
            ->whereDate(
                'check_in',
                '<',
                $out
            )
            ->whereDate(
                'check_out',
                '>',
                $in
            )
            ->exists();

        if ($bookingConflict) {
            return false;
        }

        $holdConflict = BookingHold::query()
            ->active()
            ->where(
                'property_id',
                $propertyId
            )
            ->when(
                $ignoreHold,
                fn (Builder $query) =>
                    $query->where(
                        'token',
                        '!=',
                        $ignoreHold
                    )
            )
            ->whereDate(
                'check_in',
                '<',
                $out
            )
            ->whereDate(
                'check_out',
                '>',
                $in
            )
            ->exists();

        if ($holdConflict) {
            return false;
        }

        $maintenanceConflict = MaintenancePeriod::query()
            ->where(
                'property_id',
                $propertyId
            )
            ->where(
                'blocks_booking',
                true
            )
            ->whereDate(
                'starts_on',
                '<',
                $out
            )
            ->whereDate(
                'ends_on',
                '>',
                $in
            )
            ->exists();

        return ! $maintenanceConflict;
    }

    public function calendar(
        int $propertyId,
        CarbonInterface $from,
        int $days = 90
    ): array {
        $days = max(1, min($days, 366));
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = $start->addDays($days);
        $blocked = [];

        $markBlocked = static function ($fromDate, $toDate) use (&$blocked, $start, $end): void {
            $cursor = CarbonImmutable::parse($fromDate)->startOfDay()->max($start);
            $limit = CarbonImmutable::parse($toDate)->startOfDay()->min($end);

            while ($cursor->lessThan($limit)) {
                $blocked[$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        };

        Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', config('azari.booking.active_statuses', [
                'hold', 'pending', 'pending_payment', 'approved', 'confirmed', 'paid', 'check_in', 'checked_in',
            ]))
            ->where(function (Builder $query): void {
                $query->whereNotIn('status', ['pending', 'pending_payment'])
                    ->orWhere(function (Builder $pending): void {
                        $pending->whereIn('status', ['pending', 'pending_payment'])
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            })
            ->whereDate('check_in', '<', $end)
            ->whereDate('check_out', '>', $start)
            ->get(['check_in', 'check_out'])
            ->each(fn (Booking $booking) => $markBlocked($booking->check_in, $booking->check_out));

        BookingHold::query()
            ->active()
            ->where('property_id', $propertyId)
            ->whereDate('check_in', '<', $end)
            ->whereDate('check_out', '>', $start)
            ->get(['check_in', 'check_out'])
            ->each(fn (BookingHold $hold) => $markBlocked($hold->check_in, $hold->check_out));

        MaintenancePeriod::query()
            ->where('property_id', $propertyId)
            ->where('blocks_booking', true)
            ->whereDate('starts_on', '<', $end)
            ->whereDate('ends_on', '>', $start)
            ->get(['starts_on', 'ends_on'])
            ->each(fn (MaintenancePeriod $period) => $markBlocked($period->starts_on, $period->ends_on));

        $calendar = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->addDays($offset);
            $isAvailable = ! isset($blocked[$date->toDateString()]);
            $calendar[] = [
                'date' => $date,
                'available' => $isAvailable,
                'state' => $isAvailable ? 'available' : 'unavailable',
            ];
        }

        return $calendar;
    }

    public function hold(
        Property $property,
        CarbonInterface $in,
        CarbonInterface $out,
        int $adults,
        int $children,
        int $rooms,
        ?int $userId
    ): BookingHold {
        $this->assertRules(
            $property,
            $in,
            $out,
            $adults,
            $children,
            $rooms
        );

        return DB::transaction(
            function () use (
                $property,
                $in,
                $out,
                $adults,
                $children,
                $rooms,
                $userId
            ) {
                Property::query()
                    ->whereKey(
                        $property->getKey()
                    )
                    ->when(
                        DB::connection()
                            ->getDriverName() !== 'sqlite',
                        fn (Builder $query) =>
                            $query->lockForUpdate()
                    )
                    ->firstOrFail();

                BookingHold::query()
                    ->where(
                        'expires_at',
                        '<=',
                        now()
                    )
                    ->delete();

                if (
                    ! $this->available(
                        $property->getKey(),
                        $in,
                        $out
                    )
                ) {
                    throw ValidationException::withMessages([
                        'property_id' =>
                            'Residence is no longer available.',
                    ]);
                }

                return BookingHold::query()->create([
                    'property_id' =>
                        $property->getKey(),
                    'user_id' =>
                        $userId,
                    'check_in' =>
                        $in,
                    'check_out' =>
                        $out,
                    'adults' =>
                        $adults,
                    'children' =>
                        $children,
                    'rooms' =>
                        $rooms,
                    'expires_at' =>
                        now()->addMinutes(
                            max(
                                1,
                                (int) config(
                                    'azari.booking.hold_minutes',
                                    15
                                )
                            )
                        ),
                ]);
            },
            3
        );
    }
}
