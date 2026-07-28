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

        return DB::transaction(function () use ($user, $profile, $currency, $amount, $ownerNote): WithdrawalRequest {
            User::query()
                ->whereKey($user->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $available = $this->balances->available($user, $currency);

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
                ],
                'owner_note' => $ownerNote,
                'requested_at' => now(),
            ]);
        }, 5);
    }

    public function process(WithdrawalRequest $withdrawal, User $processor, ?string $adminNote = null): WithdrawalRequest
    {
        $claimed = DB::transaction(function () use ($withdrawal, $processor, $adminNote): WithdrawalRequest {
            $locked = WithdrawalRequest::query()
                ->whereKey($withdrawal->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('This withdrawal is no longer pending and cannot be processed again.');
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
            // Gateways must send the withdrawal reference as their provider idempotency key.
            $result = $this->gateways->send($claimed);
        } catch (Throwable $exception) {
            report($exception);

            $claimed->update([
                'status' => 'failed',
                'failed_at' => now(),
                'last_error' => Str::limit($exception->getMessage(), 4000),
            ]);

            throw new RuntimeException('Payout failed safely before confirmation: '.$exception->getMessage(), 0, $exception);
        }

        try {
            return DB::transaction(function () use ($claimed, $result, $processor, $adminNote): WithdrawalRequest {
                $locked = WithdrawalRequest::query()
                    ->whereKey($claimed->id)
                    ->when(
                        DB::connection()->getDriverName() !== 'sqlite',
                        fn ($query) => $query->lockForUpdate()
                    )
                    ->firstOrFail();

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

                OwnerLedgerEntry::query()->firstOrCreate(
                    ['withdrawal_request_id' => $locked->id, 'type' => 'withdrawal'],
                    [
                        'user_id' => $locked->user_id,
                        'type' => 'withdrawal',
                        'direction' => 'debit',
                        'amount' => $locked->amount,
                        'currency' => $locked->currency,
                        'reference' => 'DEBIT-'.Str::upper(Str::random(14)),
                        'description' => 'Withdrawal '.$locked->reference.' processed through '.ucfirst($locked->gateway).'.',
                        'metadata' => ['provider_reference' => $providerReference],
                    ]
                );

                $locked->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                return $locked->refresh();
            }, 5);
        } catch (Throwable $exception) {
            report($exception);

            // Money may already have left the provider. Never mark this as safely retryable.
            $claimed->refresh()->update([
                'status' => 'reconciliation_required',
                'reconciliation_required_at' => now(),
                'last_error' => Str::limit($exception->getMessage(), 4000),
                'provider_reference' => $result['reference'] ?? $claimed->provider_reference,
                'provider_response' => $result['safe_response'] ?? $claimed->provider_response,
            ]);

            throw new RuntimeException(
                'The provider may have sent this payout, but Azari could not finish recording it. Manual reconciliation is required; do not retry automatically.',
                0,
                $exception
            );
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'WDR-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (WithdrawalRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
