<?php

namespace App\Services\Owners;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayoutProfile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Withdrawals\WithdrawalGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OwnerWithdrawalService
{
    public function __construct(
        private readonly OwnerBalanceService $balances,
        private readonly WithdrawalGatewayManager $gateways,
    ) {}

    public function request(
        User $user,
        OwnerPayoutProfile $profile,
        string $currency,
        float $amount,
        ?string $ownerNote = null,
    ): WithdrawalRequest {
        $currency = strtoupper($currency);
        $amount = round($amount, 2);

        // A verified destination is not enough: it must belong to the
        // withdrawing user. Never let a caller substitute someone else's
        // verified payout profile.
        if ((int) $profile->user_id !== (int) $user->getKey()) {
            throw new RuntimeException('The payout destination is not authorized for this owner.');
        }

        if (! $profile->is_verified) {
            throw new RuntimeException('Your payout destination must be verified before you can request a withdrawal.');
        }

        return DB::transaction(function () use ($user, $profile, $currency, $amount, $ownerNote): WithdrawalRequest {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $available = $this->balances->available($lockedUser, $currency);

            if ($amount <= 0 || $amount > $available + 0.001) {
                throw new RuntimeException('The requested amount exceeds your available balance.');
            }

            return WithdrawalRequest::query()->create([
                'reference' => $this->reference(),
                'idempotency_key' => 'owner-withdrawal-request-'.$user->id.'-'.Str::uuid(),
                'user_id' => $user->id,
                'gateway' => $profile->preferred_gateway,
                'currency' => $currency,
                'amount' => $amount,
                'status' => 'pending',
                'destination_snapshot' => [
                    'paypal_recipient' => $profile->paypal_recipient,
                    'paypal_recipient_type' => $profile->paypal_recipient_type,
                    'stripe_connected_account_id' => $profile->stripe_connected_account_id,
                    'verified_at' => optional($profile->verified_at)->toIso8601String(),
                ],
                'owner_note' => $ownerNote,
                'requested_at' => now(),
            ]);
        }, 5);
    }

    public function process(WithdrawalRequest $withdrawal, User $processor, ?string $adminNote = null): WithdrawalRequest
    {
        $claimed = DB::transaction(function () use ($withdrawal, $processor, $adminNote): WithdrawalRequest {
            $locked = $this->lock($withdrawal);

            if ($locked->status !== 'pending') {
                throw new RuntimeException('This withdrawal is no longer pending and cannot be processed again.');
            }

            // An owner may have requested this transfer before a subsequent
            // chargeback froze funds. Re-check under the same owner account
            // lock instead of treating an old pending request as payable.
            $owner = User::query()->whereKey($locked->user_id)
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn ($q) => $q->lockForUpdate())->firstOrFail();
            $pendingOthers = max(0, $this->balances->pending($owner, $locked->currency) - (float) $locked->amount);
            $availableForThisTransfer = $this->balances->balance($owner, $locked->currency)
                - $pendingOthers - $this->balances->disputeHeld($owner, $locked->currency);
            if ($availableForThisTransfer + 0.001 < (float) $locked->amount) {
                throw new RuntimeException('Withdrawal is on hold because a new dispute or balance reversal reduced settled owner funds.');
            }

            $locked->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'processed_by' => $processor->id,
                'admin_note' => $adminNote,
                'last_error' => null,
            ]);

            return $locked->refresh();
        }, 5);

        try {
            $result = $this->gateways->send($claimed);
        } catch (Throwable $exception) {
            report($exception);

            // An HTTP timeout/reset is NOT proof that the remote gateway
            // rejected the payout. Mark the outcome uncertain and reserve
            // funds until a human verifies the provider ledger. Otherwise a
            // retry could pay the owner twice.
            DB::transaction(function () use ($claimed, $exception): void {
                $locked = $this->lock($claimed);
                if ($locked->status === 'processing') {
                    $locked->update([
                        'status' => 'reconciliation_required',
                        'reconciliation_required_at' => now(),
                        'last_error' => 'Unconfirmed provider dispatch: '.$exception::class,
                    ]);
                }
            }, 5);

            throw new RuntimeException(
                'Payout outcome could not be confirmed. Manual provider reconciliation is required; do not retry automatically.',
                0,
                $exception
            );
        }

        try {
            return DB::transaction(function () use ($claimed, $result, $processor, $adminNote): WithdrawalRequest {
                $locked = $this->lock($claimed);

                if ($locked->status === 'processed') {
                    return $locked;
                }

                $providerReference = (string) ($result['reference'] ?? '');
                if ($providerReference === '') {
                    throw new RuntimeException('The payout provider did not return a reference.');
                }

                $locked->update([
                    'status' => 'provider_sent',
                    'provider_reference' => $providerReference,
                    'provider_response' => $result['safe_response'] ?? null,
                    'provider_sent_at' => now(),
                    'processed_by' => $processor->id,
                    'admin_note' => $adminNote,
                    'failed_at' => null,
                    'last_error' => null,
                ]);

                $this->recordDebit($locked, $providerReference);

                $locked->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                return $locked->refresh();
            }, 5);
        } catch (Throwable $exception) {
            report($exception);

            DB::transaction(function () use ($claimed, $result, $exception): void {
                $locked = $this->lock($claimed);
                if ($locked->status !== 'processed') {
                    $locked->update([
                        'status' => 'reconciliation_required',
                        'reconciliation_required_at' => now(),
                        'last_error' => Str::limit($exception->getMessage(), 4000),
                        'provider_reference' => $result['reference'] ?? $locked->provider_reference,
                        'provider_response' => $result['safe_response'] ?? $locked->provider_response,
                    ]);
                }
            }, 5);

            throw new RuntimeException(
                'The provider may have sent this payout, but Azari could not finish recording it. Manual reconciliation is required; do not retry automatically.',
                0,
                $exception
            );
        }
    }

    public function retryFailed(WithdrawalRequest $withdrawal, User $processor, ?string $note = null): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $processor, $note): WithdrawalRequest {
            $locked = $this->lock($withdrawal);

            if (! $locked->isSafelyRetryable()) {
                throw new RuntimeException('This withdrawal is not safe to retry. Reconcile it instead.');
            }

            $locked->update([
                'status' => 'pending',
                'processing_started_at' => null,
                'failed_at' => null,
                'last_error' => null,
                'processed_by' => $processor->id,
                'admin_note' => $note,
                'retry_count' => ((int) $locked->retry_count) + 1,
            ]);

            return $locked->refresh();
        }, 5);
    }

    public function reconcileAsPaid(
        WithdrawalRequest $withdrawal,
        User $processor,
        string $providerReference,
        string $note
    ): WithdrawalRequest {
        return DB::transaction(function () use ($withdrawal, $processor, $providerReference, $note): WithdrawalRequest {
            $locked = $this->lock($withdrawal);

            if ($locked->status !== 'reconciliation_required') {
                throw new RuntimeException('Only reconciliation-required withdrawals can be confirmed manually.');
            }
            if (! $locked->processed_by || (int) $locked->processed_by === (int) $processor->getKey()) {
                throw new RuntimeException('Independent finance approval is required for an ambiguous payout outcome.');
            }

            $providerReference = trim($providerReference);
            if ($providerReference === '') {
                throw new RuntimeException('A provider reference is required.');
            }

            $locked->update([
                'provider_reference' => $providerReference,
                'processed_by' => $processor->id,
                'reconciled_by' => $processor->id,
                'reconciled_at' => now(),
                'reconciliation_note' => $note,
                'last_error' => null,
            ]);

            $this->recordDebit($locked, $providerReference);

            $locked->update([
                'status' => 'processed',
                'processed_at' => $locked->processed_at ?: now(),
            ]);

            return $locked->refresh();
        }, 5);
    }

    public function reconcileAsNotPaid(
        WithdrawalRequest $withdrawal,
        User $processor,
        string $note
    ): WithdrawalRequest {
        return DB::transaction(function () use ($withdrawal, $processor, $note): WithdrawalRequest {
            $locked = $this->lock($withdrawal);

            if ($locked->status !== 'reconciliation_required') {
                throw new RuntimeException('Only reconciliation-required withdrawals can be released manually.');
            }
            if (! $locked->processed_by || (int) $locked->processed_by === (int) $processor->getKey()) {
                throw new RuntimeException('Independent finance approval is required before releasing reserved funds.');
            }

            if (OwnerLedgerEntry::query()->where('withdrawal_request_id', $locked->id)->exists()) {
                throw new RuntimeException('A withdrawal debit already exists. It cannot be released as unpaid.');
            }

            $locked->update([
                'status' => 'failed',
                'provider_reference' => null,
                'provider_sent_at' => null,
                'processed_by' => $processor->id,
                'reconciled_by' => $processor->id,
                'reconciled_at' => now(),
                'reconciliation_note' => $note,
                'last_error' => 'Reconciled by Azari as not paid by provider.',
                'failed_at' => now(),
                'reconciliation_required_at' => null,
            ]);

            return $locked->refresh();
        }, 5);
    }

    private function recordDebit(WithdrawalRequest $withdrawal, string $providerReference): OwnerLedgerEntry
    {
        return OwnerLedgerEntry::query()->firstOrCreate(
            ['withdrawal_request_id' => $withdrawal->id, 'type' => 'withdrawal'],
            [
                'user_id' => $withdrawal->user_id,
                'type' => 'withdrawal',
                'direction' => 'debit',
                'amount' => $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'reference' => 'DEBIT-'.Str::upper(Str::random(14)),
                'description' => 'Withdrawal '.$withdrawal->reference.' processed through '.ucfirst($withdrawal->gateway).'.',
                'metadata' => ['provider_reference' => $providerReference],
            ]
        );
    }

    private function lock(WithdrawalRequest $withdrawal): WithdrawalRequest
    {
        return WithdrawalRequest::query()
            ->whereKey($withdrawal->id)
            ->when(
                DB::connection()->getDriverName() !== 'sqlite',
                fn ($query) => $query->lockForUpdate()
            )
            ->firstOrFail();
    }

    private function reference(): string
    {
        do {
            $reference = 'WDR-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (WithdrawalRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
