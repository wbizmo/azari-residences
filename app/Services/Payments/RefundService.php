<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function request(
        Payment $payment,
        float $amount,
        ?int $actorId,
        ?string $reason = null,
        ?string $idempotencyKey = null
    ): Refund {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($payment, $amount, $actorId, $reason, $idempotencyKey): Refund {
            if ($idempotencyKey) {
                $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ((int) $existing->payment_id !== (int) $payment->getKey()
                        || abs(round((float) $existing->amount, 2) - $amount) >= 0.005) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This refund request key was already used for a different payment or amount.',
                        ]);
                    }

                    return $existing;
                }
            }

            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            if (! $lockedPayment->isSuccessful()) {
                throw ValidationException::withMessages(['payment' => 'Only verified successful payments can be refunded.']);
            }

            $lockedPayment->booking()
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $alreadyCommitted = (float) Refund::query()
                ->where('payment_id', $lockedPayment->getKey())
                ->whereIn('status', ['requested', 'processing', 'successful'])
                ->sum('amount');

            $remaining = max(0, round((float) $lockedPayment->amount - $alreadyCommitted, 2));

            if ($amount > $remaining + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds the remaining refundable payment value.',
                ]);
            }

            do {
                $reference = 'RFD-'.now()->format('ymdHis').'-'.Str::upper(Str::random(7));
            } while (Refund::query()->where('reference', $reference)->exists());

            $refund = Refund::query()->create([
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'booking_id' => $lockedPayment->booking_id,
                'payment_id' => $lockedPayment->getKey(),
                'requested_by' => $actorId,
                'amount' => $amount,
                'currency' => strtoupper((string) $lockedPayment->currency),
                'status' => 'requested',
                'provider' => $lockedPayment->provider,
                'reason' => $reason,
                'requested_at' => now(),
            ]);

            AuditLog::record(
                'refund.requested',
                $refund,
                [],
                ['amount' => $amount, 'currency' => $refund->currency],
                ['payment_reference' => $lockedPayment->reference],
                $actorId
            );

            return $refund;
        }, 5);
    }

    public function markProcessing(Refund $refund, ?string $providerReference = null, ?int $actorId = null): Refund
    {
        return DB::transaction(function () use ($refund, $providerReference, $actorId): Refund {
            $locked = $this->lockRefund($refund);

            if ($locked->status === 'successful') {
                return $locked;
            }

            if (! in_array($locked->status, ['requested', 'failed', 'processing'], true)) {
                throw ValidationException::withMessages(['refund' => 'This refund cannot be moved into processing.']);
            }

            $locked->update([
                'status' => 'processing',
                'provider_reference' => $providerReference ?: $locked->provider_reference,
                'safe_error' => null,
            ]);

            AuditLog::record('refund.processing', $locked, [], ['status' => 'processing'], [], $actorId);

            return $locked->refresh();
        }, 5);
    }

    public function markSuccessful(
        Refund $refund,
        ?string $providerReference = null,
        ?int $actorId = null,
        array $metadata = []
    ): Refund {
        return DB::transaction(function () use ($refund, $providerReference, $actorId, $metadata): Refund {
            $locked = $this->lockRefund($refund);

            if ($locked->status === 'successful') {
                return $locked;
            }

            $payment = Payment::query()
                ->whereKey($locked->payment_id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            if (! $payment->isSuccessful()) {
                throw ValidationException::withMessages(['payment' => 'The source payment is no longer refundable.']);
            }

            $successfulOther = (float) Refund::query()
                ->where('payment_id', $payment->getKey())
                ->where('status', 'successful')
                ->whereKeyNot($locked->getKey())
                ->sum('amount');

            if ($successfulOther + (float) $locked->amount > (float) $payment->amount + 0.009) {
                throw ValidationException::withMessages(['refund' => 'Refund completion would exceed the source payment.']);
            }

            $locked->update([
                'status' => 'successful',
                'provider_reference' => $providerReference ?: $locked->provider_reference,
                'processed_by' => $actorId,
                'processed_at' => now(),
                'safe_error' => null,
                'metadata' => array_merge($locked->metadata ?? [], $metadata),
            ]);

            $refunded = (float) Refund::query()
                ->where('payment_id', $payment->getKey())
                ->where('status', 'successful')
                ->sum('amount');

            $payment->update(['refunded_amount' => min((float) $payment->amount, round($refunded, 2))]);

            AuditLog::record(
                'refund.successful',
                $locked,
                [],
                ['status' => 'successful', 'amount' => (float) $locked->amount],
                ['payment_reference' => $payment->reference],
                $actorId
            );

            return $locked->refresh();
        }, 5);
    }

    public function markFailed(Refund $refund, string $safeError, ?int $actorId = null): Refund
    {
        return DB::transaction(function () use ($refund, $safeError, $actorId): Refund {
            $locked = $this->lockRefund($refund);

            if ($locked->status === 'successful') {
                return $locked;
            }

            $locked->update([
                'status' => 'failed',
                'safe_error' => Str::limit(strip_tags($safeError), 500),
                'processed_by' => $actorId,
                'processed_at' => now(),
            ]);

            AuditLog::record('refund.failed', $locked, [], ['status' => 'failed'], [], $actorId);

            return $locked->refresh();
        }, 5);
    }

    private function lockRefund(Refund $refund): Refund
    {
        return Refund::query()
            ->whereKey($refund->getKey())
            ->when(
                DB::connection()->getDriverName() !== 'sqlite',
                fn (Builder $query) => $query->lockForUpdate()
            )
            ->firstOrFail();
    }
}
