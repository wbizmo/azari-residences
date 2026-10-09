<?php

namespace Tests\Feature\Bookings;

use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVerifiedReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receipt_selects_verified_payment_instead_of_latest_failed_attempt(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed', 'total' => 100]);
        $paid = Payment::query()->create([
            'booking_id' => $booking->id, 'reference' => 'PAY-RECEIPT-VERIFIED',
            'provider' => 'manual', 'status' => Payment::SUCCESSFUL,
            'amount' => 100, 'currency' => $booking->currency,
            'verified_at' => now()->subHour(), 'paid_at' => now()->subHour(),
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id, 'reference' => 'PAY-RECEIPT-FAILED',
            'provider' => 'manual', 'status' => Payment::FAILED,
            'amount' => 100, 'currency' => $booking->currency,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = app(BookingManagementController::class)->receipt($booking);
        $payment = $response->getOriginalContent()->getData()['payment'];
        $this->assertSame($paid->id, $payment->id);
        $this->assertSame(Payment::SUCCESSFUL, $payment->status);
    }
}
