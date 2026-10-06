<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AzariBookingLifecycle
{
    private const MAP = [
        'hold' => ['pending', 'cancelled', 'expired'],
        'pending' => ['pending_payment', 'approved', 'cancelled', 'expired'],
        'pending_payment' => ['confirmed', 'cancelled', 'expired'],
        'approved' => ['confirmed', 'cancelled'],
        'paid' => ['confirmed', 'checked_in', 'cancelled', 'no_show'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'check_in' => ['checked_in', 'checked_out', 'cancelled'],
        'checked_in' => ['checked_out'],
        'checked_out' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'expired' => [],
        'no_show' => [],
    ];

    public function transition(
        Booking $booking,
        string $to,
        ?int $actor = null,
        ?string $note = null
    ): Booking {
        return DB::transaction(function () use ($booking, $to, $actor, $note): Booking {
            $locked = Booking::query()
                ->whereKey($booking->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $from = (string) $locked->status;
            $allowed = self::MAP[$from] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move booking from {$from} to {$to}.",
                ]);
            }

            $updates = ['status' => $to];

            if ($to === 'approved') {
                $updates['approved_at'] = now();
            }
            if ($to === 'cancelled') {
                $updates['cancelled_at'] = now();
            }
            if ($to === 'checked_in') {
                $updates['checked_in_at'] = $locked->checked_in_at ?: now();
            }
            if ($to === 'checked_out') {
                $updates['checked_out_at'] = $locked->checked_out_at ?: now();
            }
            if ($to === 'completed') {
                $updates['completed_at'] = $locked->completed_at ?: now();
            }
            if ($to === 'no_show') {
                $updates['no_show_at'] = $locked->no_show_at ?: now();
            }
            if (in_array($to, ['approved', 'confirmed', 'checked_in'], true)) {
                $updates['room_assignment_locked_at'] = $locked->room_assignment_locked_at ?: now();
            }
            if (in_array($to, ['confirmed', 'checked_in', 'checked_out', 'completed'], true)) {
                $updates['payment_transfer_locked_at'] = $locked->payment_transfer_locked_at ?: now();
            }

            $locked->forceFill($updates)->save();

            BookingStatusHistory::query()->create([
                'booking_id' => $locked->getKey(),
                'changed_by' => $actor,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);

            return $locked->refresh();
        }, 5);
    }

    public function allowedTransitions(Booking $booking): array
    {
        return self::MAP[(string) $booking->status] ?? [];
    }

    public function assertModifiable(Booking $booking): void
    {
        if (! in_array($booking->status, config('azari.booking.modifiable_statuses'), true)) {
            throw ValidationException::withMessages(['booking' => 'Booking can no longer be modified.']);
        }
    }

    public function assertRoomTransfer(Booking $booking): void
    {
        if ($booking->room_assignment_locked_at) {
            throw ValidationException::withMessages(['property_id' => 'Residence assignment is locked.']);
        }
    }

    public function assertPaymentTransfer(Booking $booking): void
    {
        if ($booking->payment_transfer_locked_at) {
            throw ValidationException::withMessages(['payment' => 'Payment transfer is locked.']);
        }
    }
}
