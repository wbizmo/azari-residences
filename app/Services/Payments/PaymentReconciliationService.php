<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly PaymentFinalizer $finalizer,
    ) {}

    public function reconcile(Payment $payment, string $source = 'reconciliation'): Payment
    {
        if ($payment->provider === 'manual') {
            throw ValidationException::withMessages(['payment' => 'Manual payment records do not use provider reconciliation.']);
        }
        if (in_array($payment->status, [Payment::SUCCESSFUL, 'successful_excess'], true)) {
            return $payment;
        }
        if (blank($payment->provider_reference)) {
            throw ValidationException::withMessages(['payment' => 'The provider has not supplied a transaction reference yet.']);
        }

        $driver = $this->manager->driver($payment->provider);
        if (! $driver->enabled()) {
            throw ValidationException::withMessages(['payment' => ucfirst($payment->provider).' is disabled.']);
        }

        $verification = $driver->verify((string) $payment->provider_reference);
        $result = $this->finalizer->apply($payment, $verification, $source);
        AuditLog::record('payment.reconciled', $result, [], ['status' => $result->status], ['source' => $source]);

        return $result;
    }

    /** @return array{checked:int,successful:int,pending:int,failed:int,abandoned:int} */
    public function reconcilePending(int $limit = 100): array
    {
        $windowMinutes = max(
            10,
            min(
                10080,
                (int) config('azari.integrations.reconcile_window_minutes', 1440)
            )
        );
        $cutoff = now()->subMinutes($windowMinutes);

        /*
         * Provider checkouts are short-lived. Once an unresolved payment is
         * older than the reconciliation window we stop polling it forever.
         * A genuine late provider webhook is still accepted and verified by
         * PaymentWebhookProcessor/PaymentFinalizer, so abandoning stale local
         * polling does not discard a later real payment.
         */
        $abandoned = Payment::query()
            ->whereIn('provider', ['flutterwave', 'pesapal', 'intouch'])
            ->whereIn('status', ['initiated', 'pending'])
            ->whereNotNull('provider_reference')
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('initiated_at', '<=', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query
                            ->whereNull('initiated_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->update([
                'status' => 'abandoned',
                'abandoned_at' => now(),
            ]);

        $result = [
            'checked' => 0,
            'successful' => 0,
            'pending' => 0,
            'failed' => 0,
            'abandoned' => $abandoned,
        ];

        Payment::query()
            ->whereIn('provider', ['flutterwave', 'pesapal', 'intouch'])
            ->whereIn('status', ['initiated', 'pending'])
            ->whereNotNull('provider_reference')
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('initiated_at', '>', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query
                            ->whereNull('initiated_at')
                            ->where('created_at', '>', $cutoff);
                    });
            })
            ->oldest('updated_at')
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->each(function (Payment $payment) use (&$result): void {
                $result['checked']++;

                try {
                    $reconciled = $this->reconcile(
                        $payment,
                        'scheduled_reconciliation'
                    );

                    if ($reconciled->status === Payment::SUCCESSFUL) {
                        $result['successful']++;
                    } elseif ($reconciled->status === Payment::PENDING) {
                        $result['pending']++;
                    } else {
                        $result['failed']++;
                    }
                } catch (\Throwable $e) {
                    $result['failed']++;

                    Log::warning('Scheduled payment reconciliation failed.', [
                        'payment_reference' => $payment->reference,
                        'provider' => $payment->provider,
                        'exception_class' => $e::class,
                        'safe_message' => 'Provider reconciliation failed.',
                    ]);
                }
            });

        return $result;
    }
}
