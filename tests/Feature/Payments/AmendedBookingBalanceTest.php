<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\PaymentEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmendedBookingBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_payment_does_not_hide_balance_after_repricing(): void
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed', 'total' => 200,
            'paid_at' => now(), 'receipt_number' => 'RCT-ORIGINAL-1',
            'payment_reference' => 'PAY-ORIGINAL-1',
        ]);
        Payment::query()->create([
            'booking_id' => $booking->getKey(), 'provider' => 'manual',
            'reference' => 'PAY-ORIGINAL-1', 'amount' => 200,
            'currency' => 'USD', 'status' => Payment::SUCCESSFUL,
        ]);

        $this->assertSame(0.0, $booking->balanceDue());
        $booking->forceFill(['total' => 275])->save();
        $booking->refresh();

        $this->assertFalse($booking->hasLegacyPaidRecord());
        $this->assertFalse($booking->isPaid());
        $this->assertSame(75.0, $booking->balanceDue());
        $this->assertFalse(app(PaymentEligibilityService::class)->isFullyPaid($booking));
        $this->assertTrue(app(PaymentEligibilityService::class)->acceptsPayment($booking));
    }

    public function test_old_receipt_only_booking_still_counts_as_legacy_paid(): void
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed', 'total' => 350,
            'paid_at' => now(), 'receipt_number' => 'LEGACY-REC-1',
            'payment_reference' => 'LEGACY-PAY-1',
        ]);
        $this->assertTrue($booking->hasLegacyPaidRecord());
        $this->assertSame(0.0, $booking->balanceDue());
        $this->assertTrue(app(PaymentEligibilityService::class)->isFullyPaid($booking));
    }
}
