<?php

namespace App\Services\Owners;

use App\Models\AuditLog;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\PaymentDispute;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chargeback reservation and independent maker/checker resolution.
 * Only evidence-backed staff actions; a dispute notification is not proof
 * that funds have finally been lost or credited by the provider.
 */
class OwnerDisputeService
{
    public function open(
        Payment $payment,
        User $reporter,
        string $providerReference,
        string $evidenceReference,
        float $amount
    ): PaymentDispute {
        $this->assertFinanceAdmin($reporter);
        $providerReference = trim($providerReference);
        $evidenceReference = trim($evidenceReference);
        $amount = round($amount, 2);
        if ($amount <= 0 || strlen($providerReference) < 5 || strlen($evidenceReference) < 5) {
            throw ValidationException::withMessages(['dispute' => 'Provide a positive disputed amount and independently traceable evidence references.']);
        }

        return DB::transaction(function () use ($payment, $reporter, $providerReference, $evidenceReference, $amount): PaymentDispute {
            $credit = OwnerLedgerEntry::query()
                ->where('payment_id', $payment->getKey())->where('type', 'booking_earning')
                ->where('direction', 'credit')->first();
            if ($credit) {
                User::query()->whereKey($credit->user_id)
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            }

            $locked = Payment::query()->whereKey($payment->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            if (! $locked->isSuccessful()) {
                throw ValidationException::withMessages(['payment' => 'A chargeback can only be registered against a verified settled payment.']);
            }
            $duplicate = PaymentDispute::query()
                ->where('provider', $locked->provider)
                ->where('provider_dispute_reference', $providerReference)->first();
            if ($duplicate) {
                if ((int) $duplicate->payment_id !== (int) $locked->getKey()
                    || abs((float) $duplicate->amount - $amount) >= 0.005) {
                    throw ValidationException::withMessages(['dispute' => 'This provider dispute reference conflicts with an existing payment or amount.']);
                }
                return $duplicate;
            }

            $refunded = (float) Refund::query()->where('payment_id', $locked->getKey())
                ->whereIn('status', ['successful', 'processing', 'requested', 'reconciliation_required'])
                ->sum('amount');
            $alreadyDisputed = (float) PaymentDispute::query()->where('payment_id', $locked->getKey())
                ->whereIn('status', ['open', 'lost'])->sum('amount');
            if ($amount > max(0, (float) $locked->amount - $refunded - $alreadyDisputed) + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Dispute would exceed payment funds not already refunded or disputed.']);
            }
            $dispute = PaymentDispute::query()->create([
                'payment_id' => $locked->getKey(), 'owner_user_id' => $credit?->user_id,
                'reported_by' => $reporter->getKey(),
                'provider' => $locked->provider,
                'provider_dispute_reference' => $providerReference,
                'evidence_reference' => $evidenceReference,
                'amount' => $amount,
                'currency' => strtoupper((string) $locked->currency),
                'status' => 'open', 'reported_at' => now(),
            ]);

            AuditLog::record('payment.dispute_opened', $dispute, [], [
                'payment_id' => $locked->getKey(), 'currency' => $locked->currency,
                'amount' => $amount, 'owner_hold' => $credit?->user_id !== null,
            ], actorId: $reporter->getKey());

            return $dispute;
        }, 5);
    }

    public function resolve(
        PaymentDispute $dispute,
        User $reviewer,
        string $decision,
        string $evidenceReference,
        string $reason
    ): PaymentDispute {
        $this->assertFinanceAdmin($reviewer);
        if (! in_array($decision, ['won', 'lost'], true)
            || strlen(trim($evidenceReference)) < 5 || strlen(trim($reason)) < 8) {
            throw ValidationException::withMessages(['decision' => 'Provide a final provider decision and supporting reference and explanation.']);
        }
        return DB::transaction(function () use ($dispute, $reviewer, $decision, $evidenceReference, $reason): PaymentDispute {
            if ($dispute->owner_user_id) {
                User::query()->whereKey($dispute->owner_user_id)
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            }
            $locked = PaymentDispute::query()->whereKey($dispute->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['decision' => 'Dispute has already received a final decision.']);
            }
            if ((int) $locked->reported_by === (int) $reviewer->getKey()) {
                throw ValidationException::withMessages(['reviewer' => 'An independent administrator must review the disputed funds.']);
            }

            if ($decision === 'lost' && $locked->owner_user_id) {
                $credit = OwnerLedgerEntry::query()->where('payment_id', $locked->payment_id)
                    ->where('type', 'booking_earning')->where('direction', 'credit')->first();
                if ($credit) {
                    $alreadyDebited = (float) OwnerLedgerEntry::query()->where('payment_dispute_id', $locked->getKey())->sum('amount');
                    if ($alreadyDebited > 0) {
                        throw ValidationException::withMessages(['decision' => 'Chargeback reversal is already posted.']);
                    }
                    OwnerLedgerEntry::query()->create([
                        'payment_dispute_id' => $locked->getKey(),
                        'user_id' => $locked->owner_user_id,
                        'property_id' => $credit->property_id, 'booking_id' => $credit->booking_id,
                        'type' => 'chargeback_reversal', 'direction' => 'debit',
                        'amount' => min((float) $credit->amount, (float) $locked->amount),
                        'currency' => $locked->currency,
                        'reference' => 'CHARGEBACK-'.$locked->getKey(),
                        'description' => 'Provider-confirmed loss for dispute '.$locked->provider_dispute_reference.'.',
                        'metadata' => ['payment_id' => $locked->payment_id, 'reviewed_by' => $reviewer->getKey()],
                    ]);
                }
            }

            $locked->forceFill([
                'status' => $decision, 'reviewed_by' => $reviewer->getKey(),
                'review_note' => trim($reason),
                'evidence_reference' => trim($evidenceReference),
                'resolved_at' => now(),
            ])->save();
            AuditLog::record('payment.dispute_resolved', $locked, ['status' => 'open'], [
                'status' => $decision,
                'payment_id' => $locked->payment_id,
                'amount' => (float) $locked->amount,
            ], actorId: $reviewer->getKey());
            return $locked->refresh();
        }, 5);
    }

    private function assertFinanceAdmin(User $user): void
    {
        abort_unless($user->isAdministrator() && $user->hasPermission('payments.manage'), 403);
    }
}
