<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Refund;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Owners\OwnerEarningsService;
use App\Services\Owners\OwnerLedgerReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OwnerLedgerExceptionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_balances_pending_payout_reservations_and_processed_debit_exceptions(): void
    {
        $owner = User::factory()->create();
        OwnerLedgerEntry::query()->create([
            'user_id' => $owner->id, 'currency' => 'NGN',
            'reference' => 'LEDGER-AUDIT-CREDIT', 'description' => 'Synthetic ledger adjustment', 'type' => 'adjustment',
            'direction' => 'credit', 'amount' => 100,
        ]);
        $pending = WithdrawalRequest::query()->create([
            'user_id' => $owner->id, 'currency' => 'NGN',
            'gateway' => 'paypal', 'status' => 'pending', 'amount' => 30,
        ]);

        $auditor = app(OwnerLedgerReconciliationService::class);
        $report = $auditor->inspect($owner, 'NGN');
        $this->assertTrue($report['healthy']);
        $this->assertEqualsWithDelta(70, $report['unreserved_available'], 0.001);
        $this->assertFalse($report['provider_settlement_verified']);

        $pending->update(['status' => 'processed']);
        $report = $auditor->inspect($owner, 'NGN');
        $this->assertFalse($report['healthy']);
        $this->assertSame(1, $report['exceptions']['processed_without_debit']);
        $this->assertSame(1, Artisan::call('resavar:audit-owner-ledger', [
            'owner' => $owner->getKey(), 'currency' => 'NGN', '--json' => true,
        ]));

        OwnerLedgerEntry::query()->create([
            'user_id' => $owner->id, 'currency' => 'NGN',
            'reference' => 'LEDGER-AUDIT-WITHDRAWAL', 'description' => 'Synthetic payout debit',
            'withdrawal_request_id' => $pending->id,
            'type' => 'withdrawal', 'direction' => 'debit', 'amount' => 30,
        ]);
        $this->assertTrue($auditor->inspect($owner, 'NGN')['healthy']);
    }

    public function test_missing_owner_refund_reversal_is_visible_without_automatic_money_movement(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->getKey(), 'managed_for_owner' => true,
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->getKey(), 'currency' => 'NGN',
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 1000, 'currency' => 'NGN',
            'reference' => 'LEDGER-AUDIT-PAY-1', 'provider' => 'manual',
        ]);

        $auditor = app(OwnerLedgerReconciliationService::class);
        $this->assertSame(1, $auditor->inspect($owner, 'NGN')['exceptions']['uncredited_payments']);
        $credit = app(OwnerEarningsService::class)->creditForPayment($payment);
        $this->assertNotNull($credit);

        $refund = Refund::query()->create([
            'booking_id' => $booking->id, 'payment_id' => $payment->id,
            'reference' => 'LEDGER-AUDIT-RFD-1', 'amount' => 250,
            'currency' => 'NGN', 'provider' => 'manual',
            'status' => 'successful', 'requested_at' => now(),
        ]);

        $this->assertSame(1, $auditor->inspect($owner, 'NGN')['exceptions']['unreversed_refunds']);
        app(OwnerEarningsService::class)->reverseForRefund($refund);
        $this->assertTrue($auditor->inspect($owner, 'NGN')['healthy']);
        $this->assertEqualsWithDelta(750, $auditor->inspect($owner, 'NGN')['posted_balance'], 0.001);
    }
}
