<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingCancellationService
{
    public function cancel(
        Booking $booking,
        ?int $actorId,
        string $reason,
        ?string $internalNote = null,
        ?string $paymentNote = null,
        ?string $externalRefundReference = null
    ): Booking {
        return DB::transaction(function () use (
            $booking, $actorId, $reason, $internalNote, $paymentNote, $externalRefundReference
        ): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())
                ->firstOrFail();

            if (! in_array('cancelled', app(AzariBookingLifecycle::class)->allowedTransitions($locked), true)) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking is no longer eligible for cancellation.',
                ]);
            }

            $cancellationQuote = app(BookingCancellationQuoteService::class)->quote($locked);

            $previous = (string) $locked->status;
            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'cancellation_reason' => $reason,
                'cancellation_internal_note' => $internalNote,
                'cancellation_payment_note' => $paymentNote,
                'external_refund_reference' => $externalRefundReference,
            ])->save();

            BookingStatusHistory::query()->create([
                'booking_id' => $locked->getKey(),
                'changed_by' => $actorId,
                'from_status' => $previous,
                'to_status' => 'cancelled',
                'note' => $reason,
                'metadata' => [
                    'payment_status_note' => $paymentNote,
                    'external_refund_reference' => $externalRefundReference,
                    'refund_handled_externally' => filled($externalRefundReference),
                    'cancellation_quote' => $cancellationQuote,
                ],
            ]);

            AuditLog::record('booking.cancelled', $locked,
                ['status' => $previous],
                ['status' => 'cancelled'],
                ['reason' => $reason],
                $actorId
            );

            // The cancellation itself does NOT imply a settled refund.
            // A verified provider refund must be tracked separately through
            // RefundService before the guest is told the money was returned.
            return $locked->refresh();
        }, 5);
    }
}
