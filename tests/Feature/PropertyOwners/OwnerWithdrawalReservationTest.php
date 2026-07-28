<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayoutProfile;
use App\Models\User;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerWithdrawalService;
use App\Services\Withdrawals\WithdrawalGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OwnerWithdrawalReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_withdrawal_reserves_available_balance(): void
    {
        $user = User::factory()->create();
        $profile = OwnerPayoutProfile::query()->create([
            'user_id' => $user->id,
            'preferred_gateway' => 'paypal',
            'paypal_recipient' => 'owner@example.com',
            'paypal_recipient_type' => 'EMAIL',
        ]);

        OwnerLedgerEntry::query()->create([
            'user_id' => $user->id,
            'type' => 'booking_earning',
            'direction' => 'credit',
            'amount' => 100,
            'currency' => 'USD',
            'reference' => 'EARN-TEST-001',
            'description' => 'Test earning',
        ]);

        $gateway = Mockery::mock(WithdrawalGatewayManager::class);
        $service = new OwnerWithdrawalService(app(OwnerBalanceService::class), $gateway);

        $service->request($user, $profile, 'USD', 80);

        $this->assertSame(20.0, app(OwnerBalanceService::class)->available($user, 'USD'));

        $this->expectException(RuntimeException::class);
        $service->request($user, $profile, 'USD', 30);
    }
}
