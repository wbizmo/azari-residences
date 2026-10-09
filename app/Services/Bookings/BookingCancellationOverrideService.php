<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingCancellationOverride;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payments\RefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingCancellationOverrideService
{
    public function __construct(private readonly RefundService $refunds) {}

    public function request(Booking $booking, User $requester, float $amount, string $reason): BookingCancellationOverride
    {
        abort_unless($requester->isStaff() && $requester->hasPermission('bookings.edit'), 403);

        $amount = round($amount, 2);
        $reason = trim($reason);
        if ($amount <= 0 || mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['amount' => 'Supply a positive refund amount and a detailed justification.']);
        }

        return DB::transaction(function () use ($booking, $requester, $amount, $reason): BookingCancellationOverride {
            $locked = $this->lockBooking($booking);
            $this->assertTerminal($locked);
            if ($amount > $this->refundableHeadroom($locked) + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'The exception exceeds unreserved, verified refundable funds.',
                ]);
            }
            if (BookingCancellationOverride::query()
                ->where('booking_id', $locked->getKey())
                ->where('status', 'requested')
                ->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages([
                    'booking' => 'An existing cancellation exception is awaiting independent review.',
                ]);
            }

            $override = BookingCancellationOverride::query()->create([
                'booking_id' => $locked->getKey(),
                'requested_by' => $requester->getKey(),
                'amount' => $amount,
                'currency' => strtoupper((string) $locked->currency),
                'reason' => $reason,
                'status' => 'requested',
                'expires_at' => now()->addHours(48),
            ]);
            AuditLog::record('booking.cancellation_exception_requested', $override, [], [
                'status' => 'requested', 'amount' => $amount, 'currency' => $override->currency,
            ], actorId: $requester->getKey());

            return $override;
        }, 5);
    }

    public function review(
        Booking $booking,
        BookingCancellationOverride $override,
        User $reviewer,
        string $decision
    ): BookingCancellationOverride {
        abort_unless($reviewer->isAdministrator()
            && $reviewer->hasPermission('payments.manage')
            && $reviewer->hasPermission('bookings.edit'), 403);
        abort_unless((int) $override->booking_id === (int) $booking->getKey(), 404);
        if (! in_array($decision, ['approve', 'decline'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or decline.']);
        }

        return DB::transaction(function () use ($booking, $override, $reviewer, $decision): BookingCancellationOverride {
            $lockedBooking = $this->lockBooking($booking);
            $locked = BookingCancellationOverride::query()->whereKey($override->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();

            abort_unless((int) $locked->booking_id === (int) $lockedBooking->getKey(), 404);
            if ((int) $locked->requested_by === (int) $reviewer->getKey()) {
                throw ValidationException::withMessages([
                    'reviewer' => 'An independent administrator must review this refund exception.',
                ]);
            }
            if ($locked->status === 'applied' && $decision === 'approve') {
                return $locked;
            }
            if ($locked->status !== 'requested' || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'booking' => 'This cancellation exception is expired or already reviewed.',
                ]);
            }

            if ($decision === 'decline') {
                $locked->forceFill([
                    'status' => 'declined', 'reviewed_by' => $reviewer->getKey(),
                    'reviewed_at' => now(),
                ])->save();
                AuditLog::record('booking.cancellation_exception_declined', $locked, [], [
                    'status' => 'declined',
                ], actorId: $reviewer->getKey());
                return $locked->refresh();
            }

            $this->assertTerminal($lockedBooking);
            if (strtoupper((string) $lockedBooking->currency) !== (string) $locked->currency) {
                throw ValidationException::withMessages(['currency' => 'Booking currency has changed. Reassess this request.']);
            }
            if ((float) $locked->amount > $this->refundableHeadroom($lockedBooking) + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Verified unreserved funds have changed. Request a new exception amount.',
                ]);
            }

            $remaining = round((float) $locked->amount, 2);
            foreach ($lockedBooking->payments()->where('status', Payment::SUCCESSFUL)->orderBy('id')->get() as $payment) {
                if ($remaining < 0.01) {
                    break;
                }
                $reserved = (float) $payment->refunds()
                    ->whereIn('status', ['requested', 'processing', 'reconciliation_required', 'successful'])
                    ->sum('amount');
                $available = max(0, round((float) $payment->amount - $reserved, 2));
                $part = min($remaining, $available);
                if ($part < 0.01) {
                    continue;
                }

                $this->refunds->request(
                    $payment,
                    $part,
                    $reviewer->getKey(),
                    'Independently approved cancellation exception for '.$lockedBooking->reference,
                    hash('sha256', 'cancel-override|'.$locked->getKey().'|'.$payment->getKey())
                );
                $remaining = round($remaining - $part, 2);
            }

            if ($remaining >= 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'Unable to reserve full exception amount from verified payments.',
                ]);
            }

            $locked->forceFill([
                'status' => 'applied',
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'applied_at' => now(),
            ])->save();
            AuditLog::record('booking.cancellation_exception_applied', $locked, [], [
                'status' => 'applied',
                'amount_reserved' => (float) $locked->amount,
                'currency' => $locked->currency,
                'provider_settled' => false,
            ], actorId: $reviewer->getKey());

            return $locked->refresh();
        }, 5);
    }

    private function lockBooking(Booking $booking): Booking
    {
        return Booking::query()->whereKey($booking->getKey())
            ->when(DB::connection()->getDriverName() !== 'sqlite',
                fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
    }

    private function assertTerminal(Booking $booking): void
    {
        $earlyDeparture = $booking->status === 'checked_out'
            && $booking->statusHistory()
                ->where('to_status', 'checked_out')
                ->where('metadata->early_departure', true)
                ->exists();
        if (! in_array($booking->status, ['cancelled', 'no_show'], true) && ! $earlyDeparture) {
            throw ValidationException::withMessages([
                'booking' => 'Only cancellations, no-shows or audited early departures can request a refund exception.',
            ]);
        }
    }

    private function refundableHeadroom(Booking $booking): float
    {
        // An operator-recorded external refund may already have moved money.
        // Never reserve further funds before independent reconciliation.
        if (filled($booking->external_refund_reference)) {
            throw ValidationException::withMessages([
                'amount' => 'An external refund requires manual settlement reconciliation before another exception.',
            ]);
        }

        $paid = (float) $booking->payments()->where('status', Payment::SUCCESSFUL)->sum('amount');
        $reserved = (float) $booking->refunds()
            ->whereIn('status', ['requested', 'processing', 'reconciliation_required', 'successful'])
            ->sum('amount');

        return max(0, round($paid - $reserved, 2));
    }
}
