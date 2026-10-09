<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\PaymentProviderStatus;
use App\Models\PaymentVerificationAttempt;
use App\Services\Owners\OwnerEarningsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentFinalizer
{
    public function apply(Payment $payment, array $verification, string $source = 'callback'): Payment
    {
        $reportedReference = (string) ($verification['merchant_reference'] ?? '');
        $reportedAmount = (float) ($verification['amount'] ?? 0);
        $reportedCurrency = strtoupper((string) ($verification['currency'] ?? ''));
        $providerStatus = (string) ($verification['provider_status'] ?? 'unknown');
        $normalized = (string) ($verification['status'] ?? 'pending');

        $referenceMatches = $reportedReference === '' || hash_equals($payment->reference, $reportedReference);
        $amountMatches = abs((float) $payment->amount - $reportedAmount) < 0.01;
        $currencyMatches = hash_equals(strtoupper($payment->currency), $reportedCurrency);

        $result = $normalized;
        $safeError = null;
        if (! $referenceMatches) { $result = 'reference_mismatch'; $safeError = 'Payment reference mismatch.'; }
        elseif (! $amountMatches) { $result = 'amount_mismatch'; $safeError = 'Payment amount mismatch.'; }
        elseif (! $currencyMatches) { $result = 'currency_mismatch'; $safeError = 'Payment currency mismatch.'; }

        PaymentVerificationAttempt::query()->create([
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'result' => $result,
            'provider_status' => $providerStatus,
            'reported_amount' => $reportedAmount,
            'reported_currency' => $reportedCurrency ?: null,
            'reported_reference' => $reportedReference ?: null,
            'safe_error' => $safeError,
            'safe_response' => $verification['safe_response'] ?? null,
            'attempted_at' => now(),
        ]);

        if (! in_array($result, ['successful', 'pending', 'failed'], true)) {
            if ($payment->isSuccessful() || $payment->status === 'successful_excess') {
                AuditLog::record('payment.verification_conflict_after_success', $payment, [], [], ['result' => $result, 'source' => $source]);
                return $payment->refresh();
            }
            Payment::query()->whereKey($payment->getKey())
                ->whereNotIn('status', [Payment::SUCCESSFUL, 'successful_excess'])
                ->update([
                    'status' => 'invalid',
                    'provider_reference' => $verification['provider_reference'] ?? $payment->provider_reference,
                    'provider_response_summary' => $verification['safe_response'] ?? null,
                ]);
            AuditLog::record('payment.verification_rejected', $payment, [], [], ['result' => $result, 'source' => $source]);
            return $payment->refresh();
        }

        if ($result === 'pending') {
            if (! $payment->isSuccessful() && $payment->status !== 'successful_excess') {
                Payment::query()->whereKey($payment->getKey())
                    ->whereNotIn('status', [Payment::SUCCESSFUL, 'successful_excess'])
                    ->update([
                        'status' => 'pending',
                        'provider_reference' => $verification['provider_reference'] ?? $payment->provider_reference,
                        'provider_response_summary' => $verification['safe_response'] ?? null,
                    ]);
            }
            return $payment->refresh();
        }

        if ($result === 'failed') {
            if (! $payment->isSuccessful() && $payment->status !== 'successful_excess') {
                Payment::query()->whereKey($payment->getKey())
                    ->whereNotIn('status', [Payment::SUCCESSFUL, 'successful_excess'])
                    ->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'provider_reference' => $verification['provider_reference'] ?? $payment->provider_reference,
                        'provider_response_summary' => $verification['safe_response'] ?? null,
                    ]);
            }
            AuditLog::record('payment.failed', $payment, [], [], ['source' => $source]);
            return $payment->refresh();
        }

        return DB::transaction(function () use ($payment, $verification, $source): Payment {
            // All payment writers/refund allocators lock booking first,
            // then payment. Reverse order deadlocks under callback/refund
            // contention even when both operations are individually atomic.
            $booking = Booking::query()->whereKey($payment->booking_id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )->firstOrFail();

            $locked = Payment::query()->whereKey($payment->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )->firstOrFail();

            if ($locked->isSuccessful() || $locked->status === 'successful_excess') {
                return $locked;
            }
            if ($booking->status === 'cancelled') {
                $paidAt = filled($verification['paid_at'] ?? null)
                    ? CarbonImmutable::parse((string) $verification['paid_at'])
                    : now();
                $receipt = $locked->receipt_number ?: $this->receiptNumber($locked);

                $locked->update([
                    'status' => 'successful_excess',
                    'provider_reference' => $verification['provider_reference'] ?? $locked->provider_reference,
                    'payment_method' => $verification['payment_method'] ?? $locked->payment_method,
                    'paid_at' => $paidAt,
                    'verified_at' => now(),
                    'failed_at' => null,
                    'provider_response_summary' => $verification['safe_response'] ?? null,
                    'receipt_number' => $receipt,
                    'administrative_note' => trim(
                        ($locked->administrative_note ? $locked->administrative_note."\n" : '').
                        'Provider reported a successful payment after the booking had already been cancelled. Do not reactivate the booking automatically; review and refund or resolve manually.'
                    ),
                ]);

                AuditLog::record(
                    'payment.successful_after_booking_cancelled',
                    $locked,
                    [],
                    ['status' => 'successful_excess'],
                    ['source' => $source, 'booking_status' => $booking->status]
                );

                return $locked->refresh();
            }

            // Amendment top-up is held as verified, UNALLOCATED money until the
            // guest explicitly accepts the still-bookable quoted dates. Never
            // count it as ordinary booking payment or owner earnings before then.
            if ($locked->payment_kind === 'amendment') {
                $related = \App\Models\BookingModificationRequest::query()
                    ->where('payment_id', $locked->getKey())
                    ->where('booking_id', $booking->getKey())
                    ->first();
                if (! $related) {
                    throw new \LogicException('Verified amendment payment is missing its change request.');
                }
                $paidAt = filled($verification['paid_at'] ?? null)
                    ? CarbonImmutable::parse((string) $verification['paid_at'])
                    : now();
                $locked->forceFill([
                    'status' => 'successful_excess',
                    'provider_reference' => $verification['provider_reference'] ?? $locked->provider_reference,
                    'payment_method' => $verification['payment_method'] ?? $locked->payment_method,
                    'paid_at' => $paidAt,
                    'verified_at' => now(),
                    'failed_at' => null,
                    'provider_response_summary' => $verification['safe_response'] ?? null,
                    'receipt_number' => $locked->receipt_number ?: $this->receiptNumber($locked),
                    'administrative_note' => 'Amendment top-up verified but not allocated. Await guest acceptance and live inventory recheck; refund if offer cannot be completed.',
                ])->save();
                AuditLog::record('payment.amendment_topup_verified_unallocated', $locked, [], [
                    'status' => 'successful_excess', 'change_id' => $related->getKey(),
                ], ['source' => $source]);
                return $locked->refresh();
            }

            $alreadyAllocated = (float) $booking->payments()
                ->where('status', Payment::SUCCESSFUL)
                ->where('id', '!=', $locked->id)
                ->sum('amount');
            $remainingBeforeThisPayment = max(0, round((float) $booking->total - $alreadyAllocated, 2));
            $paidAt = filled($verification['paid_at'] ?? null)
                ? CarbonImmutable::parse((string) $verification['paid_at'])
                : now();

            if ((float) $locked->amount > $remainingBeforeThisPayment + 0.01) {
                $receipt = $locked->receipt_number ?: $this->receiptNumber($locked);
                $locked->update([
                    'status' => 'successful_excess',
                    'provider_reference' => $verification['provider_reference'] ?? $locked->provider_reference,
                    'payment_method' => $verification['payment_method'] ?? $locked->payment_method,
                    'paid_at' => $paidAt,
                    'verified_at' => now(),
                    'failed_at' => null,
                    'provider_response_summary' => $verification['safe_response'] ?? null,
                    'receipt_number' => $receipt,
                    'administrative_note' => trim(($locked->administrative_note ? $locked->administrative_note."\n" : '').'Provider reported success after the booking balance had already been satisfied. Review the excess payment and process any required refund through the Resavar refund workflow.'),
                ]);
                AuditLog::record('payment.successful_excess_detected', $locked, [], ['status' => 'successful_excess'], ['source' => $source, 'remaining_before_payment' => $remainingBeforeThisPayment]);
                return $locked->refresh();
            }

            $receipt = $locked->receipt_number ?: $this->receiptNumber($locked);
            $locked->update([
                'status' => Payment::SUCCESSFUL,
                'provider_reference' => $verification['provider_reference'] ?? $locked->provider_reference,
                'payment_method' => $verification['payment_method'] ?? $locked->payment_method,
                'paid_at' => $paidAt,
                'verified_at' => now(),
                'failed_at' => null,
                'provider_response_summary' => $verification['safe_response'] ?? null,
                'receipt_number' => $receipt,
            ]);

            $booking->refresh();
            $schedule = app(PaymentScheduleService::class)->forBooking($booking);

            if (
                $schedule['confirmation_threshold_met']
                && ! in_array($booking->status, ['cancelled', 'completed', 'checked_out', 'no_show'], true)
            ) {
                $from = $booking->status;
                $booking->update([
                    'status' => 'confirmed',
                    'paid_at' => $schedule['fully_paid']
                        ? ($booking->paid_at ?: $paidAt)
                        : $booking->paid_at,
                    'payment_reference' => $locked->reference,
                    'receipt_number' => $schedule['fully_paid']
                        ? ($booking->receipt_number ?: $receipt)
                        : $booking->receipt_number,
                    'expires_at' => null,
                    'payment_transfer_locked_at' => now(),
                ]);
                /*
                 * AZARI_SUCCESSFUL_BOOKING_ACCOUNT_V1
                 *
                 * Full provider-verified payment owns the customer-account
                 * provisioning boundary. Pending or merely initiated payment
                 * must never create a customer account.
                 */
                $account = app(
                    \App\Services\Bookings\SuccessfulBookingAccountService::class
                )->provision(
                    $booking->refresh()
                );

                if ($account['created']) {
                    $activationUser = $account['user'];
                    $activationBooking = $account['booking'];

                    DB::afterCommit(
                        function () use (
                            $activationUser,
                            $activationBooking
                        ): void {
                            app(
                                \App\Services\Bookings\SuccessfulBookingAccountService::class
                            )->sendActivation(
                                $activationUser,
                                $activationBooking
                            );
                        }
                    );
                }

                if ($from !== 'confirmed') {
                    BookingStatusHistory::query()->create([
                        'booking_id' => $booking->id,
                        'changed_by' => null,
                        'from_status' => $from,
                        'to_status' => 'confirmed',
                        'note' => $schedule['fully_paid']
                            ? 'Booking confirmed automatically after verified full payment.'
                            : 'Booking confirmed after the required payment threshold was verified.',
                        'metadata' => [
                            'provider' => $locked->provider,
                            'payment_reference' => $locked->reference,
                            'source' => $source,
                            'payment_type' => $schedule['payment_type'],
                            'paid' => $schedule['paid'],
                            'balance' => $schedule['balance'],
                        ],
                    ]);

                    app(\App\Services\Analytics\AnalyticsTracker::class)->track(
                        'booking_confirmed',
                        [
                            'user_id' => $booking->user_id,
                            'booking_id' => $booking->getKey(),
                            'property_id' => $booking->property_id,
                            'accommodation_type_id' => $booking->accommodation_type_id,
                            'rate_plan_id' => $booking->rate_plan_id,
                            'source' => 'payment_finalizer',
                            'payload' => [
                                'payment_type' => $schedule['payment_type'],
                                'fully_paid' => $schedule['fully_paid'],
                            ],
                        ],
                        hash('sha256', 'booking-confirmed|'.$booking->getKey())
                    );
                }
            }

            PaymentProviderStatus::query()->updateOrCreate(
                ['provider' => $locked->provider],
                [
                    'enabled' => true,
                    'mode' => config('azari.payments.'.$locked->provider.'.mode'),
                    'connection_status' => 'successful',
                    'last_successful_payment_at' => now(),
                    'safe_message' => 'Last payment verified successfully.',
                ],
            );
            AuditLog::record(
                'payment.verified_successful',
                $locked,
                [],
                ['status' => Payment::SUCCESSFUL],
                ['source' => $source]
            );

            $finalizedPayment = $locked->refresh();

            app(OwnerEarningsService::class)
                ->creditForPayment($finalizedPayment);

            return $finalizedPayment;
        }, 3);
    }

    private function receiptNumber(Payment $payment): string
    {
        do {
            $number = 'RCT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Payment::query()->where('receipt_number', $number)->exists());
        return $number;
    }
}
