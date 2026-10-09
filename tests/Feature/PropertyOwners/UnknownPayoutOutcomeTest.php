<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\OwnerPayoutProfile;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerWithdrawalService;
use App\Services\Withdrawals\WithdrawalGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UnknownPayoutOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeout_during_external_dispatch_requires_manual_reconciliation_not_safe_retry(): void
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        // A payout being dispatched must have real prior owner earnings;
        // the new dispute balance guard correctly rejects unbacked requests.
        \App\Models\OwnerLedgerEntry::query()->create([
            'user_id' => $owner->id, 'type' => 'booking_earning',
            'direction' => 'credit', 'amount' => 100, 'currency' => 'USD',
            'reference' => 'EARN-UNCONFIRMED-PAYOUT-TEST', 'description' => 'Verified owner earnings',
        ]);
        $withdrawal = WithdrawalRequest::query()->create([
            'user_id' => $owner->id,
            'gateway' => 'paypal',
            'currency' => 'USD',
            'amount' => 100,
            'status' => 'pending',
            'destination_snapshot' => ['paypal_recipient' => 'owner@example.com'],
        ]);

        $gateway = Mockery::mock(WithdrawalGatewayManager::class);
        $gateway->shouldReceive('send')->once()->andThrow(new RuntimeException('Connection reset after request was submitted.'));
        $service = new OwnerWithdrawalService(app(OwnerBalanceService::class), $gateway);

        try {
            $service->process($withdrawal, $operator);
            $this->fail('An ambiguous payout cannot be treated as a confirmed failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Manual provider reconciliation', $exception->getMessage());
        }

        $withdrawal->refresh();
        $this->assertSame('reconciliation_required', $withdrawal->status);
        $this->assertNotNull($withdrawal->reconciliation_required_at);
        $this->assertFalse($withdrawal->isSafelyRetryable());
    }

    public function test_owner_cannot_request_withdrawal_to_another_owners_verified_profile(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $foreignProfile = OwnerPayoutProfile::query()->create([
            'user_id' => $otherOwner->id,
            'preferred_gateway' => 'paypal',
            'paypal_recipient' => 'someone-else@example.test',
            'paypal_recipient_type' => 'EMAIL',
            'is_verified' => true,
            'verified_at' => now(),
        ]);
        $gateway = Mockery::mock(WithdrawalGatewayManager::class);
        $service = new OwnerWithdrawalService(app(OwnerBalanceService::class), $gateway);

        try {
            $service->request($owner, $foreignProfile, 'USD', 50);
            $this->fail('The owner must not send funds to a foreign verified payout profile.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not authorized', $exception->getMessage());
        }

        $this->assertSame(0, WithdrawalRequest::query()->where('user_id', $owner->id)->count());
    }
}
