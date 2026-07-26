<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\PaymentProviderStatus;
use App\Models\PaymentVerificationAttempt;
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
            $payment->update([
                'status' => 'invalid',
                'provider_reference' => $verification['provider_reference'] ?? $payment->provider_reference,
                'provider_response_summary' => $verification['safe_response'] ?? null,
            ]);
            AuditLog::record('payment.verification_rejected', $payment, [], [], ['result' => $result, 'source' => $source]);
            return $payment->refresh();
        }

        if ($result === 'pending') {
            if (! $payment->isSuccessful()) {
                $payment->update([
                    'status' => 'pending',
                    'provider_reference' => $verification['provider_reference'] ?? $payment->provider_reference,
                    'provider_response_summary' => $verification['safe_response'] ?? null,
                ]);
            }
            return $payment->refresh();
        }

        if ($result === 'failed') {
            if (! $payment->isSuccessful()) {
                $payment->update([
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
            $locked = 
        Payment::query()
                ->whereKey($payment->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail()
    ;
            if ($locked->isSuccessful() || $locked->status === 'successful_excess') return $locked;

            $booking = 
        $locked->booking()
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail()
    ;
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
                    'administrative_note' => trim(($locked->administrative_note ? $locked->administrative_note."\n" : '').'Provider reported success after the booking balance had already been satisfied. Review externally; no refund workflow exists in Azari.'),
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

            $remaining = max(0, round((float) $booking->total - (float) $booking->payments()->where('status', Payment::SUCCESSFUL)->sum('amount'), 2));
            if ($remaining <= 0 && ! in_array($booking->status, ['cancelled', 'completed', 'checked_out', 'no_show'], true)) {
                $from = $booking->status;
                $booking->update([
                    'status' => 'confirmed',
                    'paid_at' => $booking->paid_at ?: $paidAt,
                    'payment_reference' => $locked->reference,
                    'receipt_number' => $booking->receipt_number ?: $receipt,
                    'expires_at' => null,
                    'payment_transfer_locked_at' => now(),
                ]);
                if ($from !== 'confirmed') {
                    BookingStatusHistory::query()->create([
                        'booking_id' => $booking->id,
                        'changed_by' => null,
                        'from_status' => $from,
                        'to_status' => 'confirmed',
                        'note' => 'Booking confirmed automatically after verified payment success.',
                        'metadata' => ['provider' => $locked->provider, 'payment_reference' => $locked->reference, 'source' => $source],
                    ]);
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
            AuditLog::record('payment.verified_successful', $locked, [], ['status' => Payment::SUCCESSFUL], ['source' => $source]);
            return $locked->refresh();
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
