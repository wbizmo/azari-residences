<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayoutProfile;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerDisputeService;
use App\Services\Owners\OwnerEarningsService;
use App\Services\Owners\OwnerWithdrawalService;
use App\Services\Withdrawals\WithdrawalGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OwnerDisputeReservationTest extends TestCase
{
    use RefreshDatabase;

    private function setupPayment(): array
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id, 'managed_for_owner' => true]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'currency' => 'NGN']);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id, 'provider' => 'manual',
            'reference' => 'PAY-CHARGEBACK-001', 'status' => Payment::SUCCESSFUL,
            'currency' => 'NGN', 'amount' => 1000, 'verified_at' => now(),
        ]);
        app(OwnerEarningsService::class)->creditForPayment($payment);
        $reporter = User::factory()->create(['is_admin' => true, 'staff_role' => 'administrator']);
        $reviewer = User::factory()->create(['is_admin' => true, 'staff_role' => 'administrator']);
        return [$owner, $payment, $reporter, $reviewer];
    }

    public function test_reported_dispute_reserves_owner_balance_and_two_person_resolution_posts_once(): void
    {
        [$owner, $payment, $reporter, $reviewer] = $this->setupPayment();
        $service = app(OwnerDisputeService::class);
        $balance = app(OwnerBalanceService::class);
        $this->assertSame(1000.0, $balance->available($owner, 'NGN'));
        $dispute = $service->open($payment, $reporter, 'chargeback-case-123', 'provider-evidence-123', 350);
        $this->assertSame('open', $dispute->status);
        $this->assertSame(350.0, $balance->disputeHeld($owner, 'NGN'));
        $this->assertSame(650.0, $balance->available($owner, 'NGN'));
        $this->assertSame($dispute->id, $service->open($payment, $reporter, 'chargeback-case-123', 'provider-evidence-123', 350)->id);
        try {
            $service->resolve($dispute, $reporter, 'lost', 'provider-loss-123', 'Confirmed final chargeback');
            $this->fail('Maker cannot approve their own chargeback loss.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reviewer', $e->errors());
        }

        $resolved = $service->resolve($dispute, $reviewer, 'lost', 'provider-loss-123', 'Confirmed final chargeback');
        $this->assertSame('lost', $resolved->status);
        $this->assertSame(0.0, $balance->disputeHeld($owner, 'NGN'));
        $this->assertSame(650.0, $balance->available($owner, 'NGN'));
        $this->assertSame(1, OwnerLedgerEntry::query()->where('payment_dispute_id', $dispute->getKey())->count());
        $this->expectException(ValidationException::class);
        $service->resolve($dispute->fresh(), $reviewer, 'lost', 'provider-loss-123', 'Duplicate decision');
    }

    public function test_won_dispute_releases_hold_without_ledger_debit(): void
    {
        [$owner, $payment, $reporter, $reviewer] = $this->setupPayment();
        $service = app(OwnerDisputeService::class);
        $dispute = $service->open($payment, $reporter, 'chargeback-case-456', 'provider-evidence-456', 250);
        $service->resolve($dispute, $reviewer, 'won', 'provider-win-456', 'Bank rejected the chargeback');
        $this->assertSame(1000.0, app(OwnerBalanceService::class)->available($owner, 'NGN'));
        $this->assertSame(0, OwnerLedgerEntry::query()->where('payment_dispute_id', $dispute->getKey())->count());
    }

    public function test_new_chargeback_blocks_previous_pending_withdrawal_before_gateway_dispatch(): void
    {
        [$owner, $payment, $reporter] = $this->setupPayment();
        $profile = OwnerPayoutProfile::query()->create([
            'user_id' => $owner->getKey(), 'preferred_gateway' => 'paypal',
            'paypal_recipient' => 'owner@example.test', 'paypal_recipient_type' => 'EMAIL',
            'is_verified' => true, 'verified_at' => now(),
        ]);
        $gateway = Mockery::mock(WithdrawalGatewayManager::class);
        $gateway->shouldNotReceive('send');
        $withdrawals = new OwnerWithdrawalService(app(OwnerBalanceService::class), $gateway);
        $request = $withdrawals->request($owner, $profile, 'NGN', 900);
        app(OwnerDisputeService::class)->open($payment, $reporter, 'chargeback-case-789', 'provider-evidence-789', 300);
        $this->expectException(RuntimeException::class);
        $withdrawals->process($request, $reporter);
    }
}
