<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Refund;
use App\Models\User;
use App\Services\Owners\OwnerEarningsService;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRefundReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_refunds_reverse_earned_value_once_in_original_payment_currency(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id, 'managed_for_owner' => true]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'currency' => 'NGN']);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id, 'status' => Payment::SUCCESSFUL,
            'amount' => 1000, 'currency' => 'NGN', 'reference' => 'PAY-OWNER-NGN-1', 'provider' => 'manual',
        ]);

        $credit = app(OwnerEarningsService::class)->creditForPayment($payment);
        $this->assertNotNull($credit);
        $this->assertSame('NGN', $credit->currency);

        $refund = Refund::query()->create([
            'reference' => 'RFD-OWNER-NGN-1', 'payment_id' => $payment->id,
            'booking_id' => $booking->id, 'amount' => 250, 'currency' => 'NGN',
            'provider' => 'manual', 'status' => 'requested', 'requested_at' => now(),
        ]);

        $service = app(RefundService::class);
        $service->markSuccessful($refund);
        $service->markSuccessful($refund->fresh());

        $this->assertDatabaseHas('owner_ledger_entries', [
            'refund_id' => $refund->id, 'direction' => 'debit',
            'type' => 'refund_reversal', 'currency' => 'NGN', 'amount' => 250,
        ]);
        $this->assertSame(1, OwnerLedgerEntry::query()->where('refund_id', $refund->id)->count());
    }
}
