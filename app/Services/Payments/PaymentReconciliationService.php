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

    /** @return array{checked:int,successful:int,pending:int,failed:int} */
    public function reconcilePending(int $limit = 100): array
    {
        $result = ['checked' => 0, 'successful' => 0, 'pending' => 0, 'failed' => 0];

        Payment::query()
            ->whereIn('provider', ['flutterwave', 'pesapal', 'intouch'])
            ->whereIn('status', ['initiated', 'pending', 'failed', 'abandoned'])
            ->whereNotNull('provider_reference')
            ->oldest('updated_at')
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->each(function (Payment $payment) use (&$result): void {
                $result['checked']++;
                try {
                    $reconciled = $this->reconcile($payment, 'scheduled_reconciliation');
                    if ($reconciled->status === Payment::SUCCESSFUL) $result['successful']++;
                    elseif ($reconciled->status === Payment::PENDING) $result['pending']++;
                    else $result['failed']++;
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
