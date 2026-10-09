<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\User;
use App\Models\WithdrawalRequest;
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
}
