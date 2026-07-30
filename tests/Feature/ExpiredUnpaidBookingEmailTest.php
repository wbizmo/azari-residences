<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CommunicationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpiredUnpaidBookingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_unpaid_booking_is_cancelled_and_guest_email_is_queued_once(): void
    {
        Notification::fake();
        config()->set('mail.from.address', 'operations@azari.test');
        config()->set('azari.booking_notification_email', 'operations@azari.test');

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@azari.test',
            'status' => 'pending_payment',
            'verification_status' => 'pending',
            'expires_at' => now()->subMinute(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        $this->artisan('azari:expire-unpaid-bookings')->assertSuccessful();

        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('Payment window expired', $booking->cancellation_reason);
        $this->assertNotNull($booking->cancelled_at);

        $this->assertDatabaseHas('communication_logs', [
            'channel' => 'email',
            'template' => 'booking-expired',
            'booking_id' => $booking->id,
            'recipient' => 'guest@azari.test',
            'status' => 'queued',
        ]);

        $this->artisan('azari:expire-unpaid-bookings')->assertSuccessful();

        $this->assertSame(
            1,
            CommunicationLog::query()
                ->where('template', 'booking-expired')
                ->where('booking_id', $booking->id)
                ->where('recipient', 'guest@azari.test')
                ->count()
        );
    }

    public function test_unexpired_unpaid_booking_is_not_cancelled_or_emailed(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'future@azari.test',
            'status' => 'pending_payment',
            'verification_status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        $this->artisan('azari:expire-unpaid-bookings')->assertSuccessful();

        $this->assertSame('pending_payment', $booking->fresh()->status);
        $this->assertDatabaseMissing('communication_logs', [
            'template' => 'booking-expired',
            'booking_id' => $booking->id,
            'recipient' => 'future@azari.test',
        ]);
    }
}
