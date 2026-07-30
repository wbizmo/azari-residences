<?php

namespace App\Observers;

use App\Models\Booking;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class BookingObserver
{
    use DispatchesAfterCommit;

    public function created(Booking $booking): void
    {
        $id = $booking->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = Booking::query()->with(['property', 'user', 'payments'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->bookingCreated($fresh);
            }
        });
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        $id = $booking->getKey();
        $from = (string) $booking->getOriginal('status');
        $to = (string) $booking->status;

        $this->afterCommit(function () use ($id, $from, $to): void {
            $fresh = Booking::query()->with(['property', 'user', 'payments'])->find($id);
            if (! $fresh) {
                return;
            }

            $mail = app(AzariTransactionalMailService::class);

            if ($to === 'cancelled') {
                $mail->bookingCancelled($fresh);
            }

            if ($to === 'checked_in') {
                $mail->checkInCompleted($fresh);
            }

            if (in_array($to, ['completed', 'checked_out'], true) && ! in_array($from, ['completed', 'checked_out'], true)) {
                $mail->stayCompleted($fresh);
            }
        });
    }
}
