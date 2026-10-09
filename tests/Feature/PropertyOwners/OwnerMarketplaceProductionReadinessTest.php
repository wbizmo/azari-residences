<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayoutProfile;
use App\Models\Property;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerWithdrawalService;
use App\Services\Withdrawals\WithdrawalGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OwnerMarketplaceProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_without_owner_is_azari_owned(): void
    {
        $property = Property::factory()->create([
            'owner_id' => null,
            'owner_share_percentage' => 100,
        ]);

        $this->assertTrue($property->fresh()->isAzariOwned());
        $this->assertSame('azari', $property->fresh()->ownership_type);
        $this->assertFalse((bool) $property->fresh()->managed_for_owner);
    }

    public function test_property_with_owner_is_third_party_managed(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame('third_party', $property->fresh()->ownership_type);
        $this->assertTrue((bool) $property->fresh()->managed_for_owner);
    }

    public function test_unverified_payout_profile_cannot_request_withdrawal(): void
    {
        $owner = User::factory()->create();
        $profile = OwnerPayoutProfile::query()->create([
            'user_id' => $owner->id,
            'preferred_gateway' => 'paypal',
            'paypal_recipient' => 'owner@example.com',
            'paypal_recipient_type' => 'EMAIL',
            'is_verified' => false,
        ]);

        $service = app(OwnerWithdrawalService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be verified');

        $service->request($owner, $profile, 'USD', 50);
    }

    public function test_failed_withdrawal_with_no_provider_reference_can_be_returned_to_pending(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();

        $withdrawal = WithdrawalRequest::query()->create([
            'user_id' => $owner->id,
            'gateway' => 'paypal',
            'currency' => 'USD',
            'amount' => 50,
            'status' => 'failed',
            'destination_snapshot' => [],
            'failed_at' => now(),
        ]);

        $service = app(OwnerWithdrawalService::class);
        $updated = $service->retryFailed($withdrawal, $staff, 'Provider rejected before sending.');

        $this->assertSame('pending', $updated->status);
        $this->assertSame(1, $updated->retry_count);
    }

    public function test_reconciliation_as_paid_records_only_one_debit(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();

        $withdrawal = WithdrawalRequest::query()->create([
            'user_id' => $owner->id,
            'gateway' => 'paypal',
            'currency' => 'USD',
            'amount' => 50,
            'status' => 'reconciliation_required',
            'destination_snapshot' => [],
            'reconciliation_required_at' => now(),
            'processed_by' => User::factory()->create()->getKey(),
        ]);

        $service = app(OwnerWithdrawalService::class);
        $service->reconcileAsPaid($withdrawal, $staff, 'PAYPAL-123', 'Confirmed in provider dashboard.');

        $this->assertSame(1, OwnerLedgerEntry::query()
            ->where('withdrawal_request_id', $withdrawal->id)
            ->where('direction', 'debit')
            ->count());

        $this->assertSame('processed', $withdrawal->fresh()->status);
    }
}
