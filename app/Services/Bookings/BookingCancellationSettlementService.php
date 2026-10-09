<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payments\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Durable policy-based cancellation/refund workflow. The booking transition
 * is committed first; refunds are recoverable and idempotent in a separate
 * step to avoid holding booking and payment row locks across gateway actions.
 */
class BookingCancellationSettlementService
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly BookingCancellationQuoteService $quotes,
        private readonly RefundService $refunds,
    ) {}

    public function cancelForGuest(Booking $booking, User $guest, string $reason): Booking
    {
        abort_unless((int) $booking->user_id === (int) $guest->getKey(), 403);
        $locked = DB::transaction(function () use ($booking, $guest, $reason): Booking {
            $fresh = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            abort_unless((int) $fresh->user_id === (int) $guest->getKey(), 403);
            if (! in_array($fresh->status, ['pending', 'pending_payment', 'approved', 'paid', 'confirmed'], true)
                || $fresh->checked_in_at || ! $this->beforeArrival($fresh)) {
                throw ValidationException::withMessages([
                    'booking' => 'This reservation cannot be cancelled online after arrival or check-in. Contact support.',
                ]);
            }

            return $this->cancellations->cancel($fresh, $guest->getKey(), $reason);
        }, 5);

        // The cancellation is durable even if a provider is offline.
        // The scheduler repairs any missed financial request later.
        $this->reserveEligibleRefunds($locked);
        return $locked->refresh();
    }

    public function markNoShow(Booking $booking, User $staff, string $reason): Booking
    {
        abort_unless($staff->hasPermission('bookings.edit'), 403);
        $changed = DB::transaction(function () use ($booking, $staff, $reason): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            $tz = $locked->property_timezone ?: config('localization.platform_timezone', 'UTC');
            $localToday = now($tz)->toDateString();
            if (! in_array($locked->status, ['paid', 'confirmed'], true)
                || $locked->checked_in_at || ! $locked->check_in
                || $locked->check_in->toDateString() > $localToday) {
                throw ValidationException::withMessages([
                    'booking' => 'No-show requires an arrived, unoccupied confirmed booking.',
                ]);
            }
            $quote = $this->quotes->quote($locked, now(), noShow: true);
            $from = $locked->status;
            $locked->forceFill(['status' => 'no_show', 'no_show_at' => now()])->save();
            BookingStatusHistory::query()->create([
                'booking_id' => $locked->getKey(), 'changed_by' => $staff->getKey(),
                'from_status' => $from, 'to_status' => 'no_show', 'note' => $reason,
                'metadata' => ['cancellation_quote' => $quote, 'refund_status' => 'not_initiated'],
            ]);
            AuditLog::record('booking.no_show', $locked, ['status' => $from], [
                'status' => 'no_show', 'refund_preview' => $quote,
            ], actorId: $staff->getKey());

            return $locked->refresh();
        }, 5);

        $this->reserveEligibleRefunds($changed);
        return $changed->refresh();
    }

    /**
     * Repairable operation. Payment and refund records are re-read every time;
     * no backend gateway call is performed and provider settlement is never
     * inferred from an internal refund request.
     *
     * @return array{refunds_requested:int,manual_review:bool}
     */
    public function reserveEligibleRefunds(Booking $booking): array
    {
        // Serialize the entire entitlement assessment per booking, not just
        // individual payment requests. Without this lock, two reconciliation
        // workers can each reserve the same remainder against different
        // successful payment rows.
        return DB::transaction(function () use ($booking): array {
            $fresh = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            if (! in_array($fresh->status, ['cancelled', 'no_show'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Refunds require a terminal cancellation or no-show.',
                ]);
            }
            $quote = $this->quotes->quote(
                $fresh,
                $fresh->cancelled_at ?: $fresh->no_show_at ?: now(),
                noShow: $fresh->status === 'no_show'
            );
            if ($quote['manual_review_required'] || $quote['maximum_refund_due'] === null) {
                return ['refunds_requested' => 0, 'manual_review' => true];
            }

            // Both successful refunds and in-flight reservations consume
            // entitlement. Replaying after settlement must never issue a
            // second refund against a later payment.
            $alreadyReserved = (float) $fresh->refunds()
                ->whereIn('status', ['requested', 'processing', 'reconciliation_required', 'successful'])
                ->sum('amount');
            $remaining = round(max(0, (float) $quote['maximum_refund_due'] - $alreadyReserved), 2);
            $count = 0;

            foreach ($fresh->payments()->where('status', Payment::SUCCESSFUL)->orderBy('id')->get() as $payment) {
                if ($remaining < 0.01) {
                    break;
                }
                $key = hash('sha256', 'policy-refund|'.$fresh->getKey().'|'.$payment->getKey().'|'.$fresh->status);
                if (Refund::query()->where('idempotency_key', $key)->exists()) {
                    continue;
                }
                $paymentReserved = (float) $payment->refunds()
                    ->whereIn('status', ['requested', 'processing', 'reconciliation_required', 'successful'])
                    ->sum('amount');
                $available = max(0, round((float) $payment->amount - $paymentReserved, 2));
                $amount = min($remaining, $available);
                if ($amount < 0.01) {
                    continue;
                }

                $this->refunds->request(
                    $payment, $amount, $fresh->cancelled_by,
                    'Policy refund for '.($fresh->status === 'no_show' ? 'no-show' : 'cancellation')
                        .' '.$fresh->reference,
                    $key
                );
                $count++;
                $remaining = round($remaining - $amount, 2);
            }

            return ['refunds_requested' => $count, 'manual_review' => false];
        }, 5);
    }

    private function beforeArrival(Booking $booking): bool
    {
        $tz = $booking->property_timezone ?: config('localization.platform_timezone', 'UTC');
        return $booking->check_in !== null
            && $booking->check_in->toDateString() > now($tz)->toDateString();
    }
}
