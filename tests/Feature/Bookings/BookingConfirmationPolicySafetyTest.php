<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Services\Bookings\AzariBookingLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingConfirmationPolicySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_confirm_unpaid_booking_that_requires_prepayment(): void
    {
        $booking = Booking::factory()->create([
            'status' => 'pending_payment',
            'total' => 1000,
            'policy_snapshot' => ['payment' => ['payment_type' => 'full_prepayment']],
        ]);
        try {
            app(AzariBookingLifecycle::class)->transition($booking, 'confirmed');
            $this->fail('A manual status transition must not bypass verified payment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_confirming_deferred_policy_stays_is_allowed(): void
    {
        $booking = Booking::factory()->create([
            'status' => 'pending_payment',
            'total' => 1000,
            'policy_snapshot' => ['payment' => ['payment_type' => 'pay_at_property']],
        ]);
        $updated = app(AzariBookingLifecycle::class)->transition($booking, 'confirmed');
        $this->assertSame('confirmed', $updated->status);
    }

    public function test_cancellation_must_use_transactional_cancellation_workflow(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);
        $this->expectException(ValidationException::class);
        app(AzariBookingLifecycle::class)->transition($booking, 'cancelled');
    }
}
