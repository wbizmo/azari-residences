<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingModificationRequest;
use App\Models\Payment;
use App\Services\Payments\RefundService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpiredAmendmentRecoveryService
{
    public function __construct(private readonly RefundService $refunds) {}

    /**
     * Reserve a provider refund for verified but unallocated amendment money.
     * The original booking dates and total are not touched.
     */
    public function requestRecovery(BookingModificationRequest $request): ?\App\Models\Refund
    {
        $bookingId = (int) $request->booking_id;
        return DB::transaction(function () use ($request, $bookingId): ?\App\Models\Refund {
            $lockedBooking = Booking::query()->whereKey($bookingId)
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();
            $lockedChange = BookingModificationRequest::query()
                ->whereKey($request->getKey())->where('booking_id', $lockedBooking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();

            if ($lockedChange->status === 'approved') {
                return null; // This payment is allocated to the booking already.
            }
            if (! in_array($lockedChange->status, ['quoted', 'expired'], true)
                || ! $lockedChange->quote_expires_at
                || $lockedChange->quote_expires_at->isFuture()) {
                return null;
            }
            if ($lockedChange->status === 'quoted') {
                $lockedChange->forceFill(['status' => 'expired'])->save();
            }
            if (! $lockedChange->payment_id) {
                return null;
            }

            $payment = Payment::query()->whereKey($lockedChange->payment_id)
                ->where('booking_id', $lockedBooking->getKey())
                ->where('payment_kind', 'amendment')
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();

            if ($payment->status !== 'successful_excess' || ! $payment->verified_at) {
                // A pending checkout must be separately reconciled with the provider.
                return null;
            }
            $offer = $lockedChange->price_quote ?? [];
            if (round((float) ($offer['delta'] ?? 0), 2) !== round((float) $payment->amount, 2)
                || strtoupper((string) ($offer['currency'] ?? '')) !== strtoupper((string) $payment->currency)) {
                throw ValidationException::withMessages(['payment' => 'The amendment payment cannot be safely matched to its quote.']);
            }

            $refund = $this->refunds->request(
                $payment, (float) $payment->amount, $lockedBooking->user_id,
                'Unallocated date-change payment: offer expired without accepted inventory',
                hash('sha256', 'expired-amendment|'.$lockedChange->getKey().'|'.$payment->getKey())
            );
            $lockedChange->forceFill(['status' => 'refund_pending'])->save();

            AuditLog::record('booking.amendment_payment_refund_reserved', $lockedChange, [], [
                'booking_id' => $lockedBooking->getKey(),
                'payment_reference' => $payment->reference,
                'refund_reference' => $refund->reference,
            ]);

            return $refund;
        }, 5);
    }
}
