<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Owners\OwnerEarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerEarningsIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_payment_is_credited_only_once(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'managed_for_owner' => true,
            'owner_share_percentage' => 70,
        ]);
        $booking = Booking::factory()->create(['property_id' => $property->id]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'status' => Payment::SUCCESSFUL,
            'amount' => 100,
            'currency' => 'USD',
            'reference' => 'PAY-OWNER-TEST-001',
            'provider' => 'manual',
        ]);

        $service = app(OwnerEarningsService::class);
        $service->creditForPayment($payment);
        $service->creditForPayment($payment);

        $this->assertSame(1, OwnerLedgerEntry::query()->where('payment_id', $payment->id)->count());
        $this->assertDatabaseHas('owner_ledger_entries', [
            'payment_id' => $payment->id,
            'user_id' => $owner->id,
            'amount' => 100,
            'gross_amount' => 100,
            'owner_share_percentage' => 100,
            'azari_share_percentage' => 0,
            'azari_share_amount' => 0,
            'direction' => 'credit',
        ]);
    }
}
