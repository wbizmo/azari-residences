<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gateway-backed refund dispatch and read-back; provider acceptance ≠ funds settled.
 * Currently Flutterwave v4 is supported. Other providers require a certified adapter.
 */
class ProviderRefundExecutionService
{
    public function __construct(
        private readonly FlutterwaveService $flutterwave,
        private readonly RefundService $refunds,
    ) {}

    public function dispatch(Refund $refund, ?int $actorId = null): Refund
    {
        // Reserve the dispatch BEFORE network I/O. Never resend a possibly
        // accepted remote refund automatically after a network timeout.
        $locked = DB::transaction(function () use ($refund, $actorId): Refund {
            $row = Refund::query()->whereKey($refund->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();

            if ($row->status !== 'requested' || strtolower((string) $row->provider) !== 'flutterwave') {
                throw ValidationException::withMessages(['refund' => 'Only new Flutterwave v4 refunds can be dispatched here.']);
            }
            $payment = Payment::query()->whereKey($row->payment_id)
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();
            if ((! $payment->isSuccessful()
                && ! ($payment->status === 'successful_excess' && $payment->verified_at !== null))
                || blank($payment->provider_reference)) {
                throw ValidationException::withMessages(['refund' => 'A verified source charge reference is required before dispatch.']);
            }
            if (strtoupper((string) $row->currency) !== strtoupper((string) $payment->currency)) {
                throw ValidationException::withMessages(['refund' => 'Source payment and refund currency mismatch.']);
            }

            $row->forceFill([
                'status' => 'processing',
                'safe_error' => null,
                'metadata' => array_merge($row->metadata ?? [], [
                    'dispatch_started_at' => now()->toIso8601String(),
                    'idempotency_reference' => $row->reference,
                ]),
            ])->save();
            AuditLog::record('refund.provider_dispatch_started', $row, [], [
                'status' => 'processing', 'provider' => 'flutterwave',
            ], actorId: $actorId);
            return $row->refresh();
        }, 5);

        try {
            $payment = $locked->payment;
            $result = $this->flutterwave->createRefund(
                (string) $payment->provider_reference,
                (float) $locked->amount,
                (string) $locked->reference,
            );
            return DB::transaction(function () use ($locked, $result, $actorId): Refund {
                $row = Refund::query()->whereKey($locked->getKey())
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn (Builder $query) => $query->lockForUpdate())->firstOrFail();
                if ($row->status === 'successful') {
                    return $row;
                }
                $row->forceFill([
                    'status' => 'processing', 'provider_reference' => $result['refund_id'],
                    'metadata' => array_merge($row->metadata ?? [], ['submission_status' => $result['status']]),
                ])->save();

                AuditLog::record('refund.provider_accepted', $row, [], [
                    'status' => 'processing', 'provider_refund_id' => $result['refund_id'],
                ], actorId: $actorId);
                return $row->refresh();
            }, 5);
        } catch (\Throwable $exception) {
            // A remote timeout/reset is ambiguous. Do NOT fail and release the
            // refundable funds or automatically issue another provider call.
            DB::transaction(function () use ($locked): void {
                Refund::query()->whereKey($locked->getKey())->where('status', 'processing')
                    ->whereNull('provider_reference')
                    ->update([
                        'status' => 'reconciliation_required',
                        'safe_error' => 'Provider submission outcome is unknown. Manual reconciliation required.',
                        'updated_at' => now(),
                    ]);
            }, 5);
            report($exception);
            throw new PaymentProviderException(
                'Refund dispatch could not be confirmed. Do not retry until provider records are reconciled.',
                'flutterwave'
            );
        }
    }

    public function reconcile(Refund $refund, ?int $actorId = null): Refund
    {
        $refund->refresh();
        if ($refund->status === 'successful') {
            return $refund;
        }
        if (strtolower((string) $refund->provider) !== 'flutterwave'
            || ! in_array($refund->status, ['processing', 'reconciliation_required'], true)
            || blank($refund->provider_reference)) {
            throw ValidationException::withMessages(['refund' => 'A provider refund ID is required before status verification.']);
        }
        $payment = $refund->payment;
        $result = $this->flutterwave->retrieveRefund((string) $refund->provider_reference);
        if ((string) $result['charge_id'] !== (string) $payment->provider_reference
            || abs((float) $result['amount'] - (float) $refund->amount) > 0.009) {
            throw ValidationException::withMessages(['refund' => 'Provider refund amount or charge identifier did not match.']);
        }

        return match ($result['status']) {
            'succeeded', 'completed' => $this->refunds->markSuccessful(
                $refund, $result['refund_id'], $actorId, [
                    'verified_from_provider' => true, 'provider_status' => $result['status'],
                ]
            ),
            'failed', 'cancelled', 'canceled' => $this->refunds->markFailed(
                $refund, 'Flutterwave reports that this refund did not settle.', $actorId, providerVerified: true
            ),
            default => $refund->fresh(),
        };
    }
}
