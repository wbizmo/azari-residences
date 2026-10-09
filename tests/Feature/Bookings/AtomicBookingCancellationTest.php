<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AtomicBookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_writes_one_state_transition_and_preserves_refund_as_separate_action(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);
        $admin = User::factory()->create();

        $updated = app(BookingCancellationService::class)->cancel(
            $booking, $admin->getKey(), 'Property unavailable',
            'Contact guest for relocation.', 'Refund assessment pending.'
        );

        $this->assertSame('cancelled', $updated->status);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->getKey(),
            'to_status' => 'cancelled',
            'changed_by' => $admin->getKey(),
        ]);
        $this->assertSame(1, $booking->statusHistory()->where('to_status', 'cancelled')->count());
        $this->assertSame(0, $booking->refunds()->count());

        $this->expectException(ValidationException::class);
        app(BookingCancellationService::class)->cancel($booking, $admin->getKey(), 'Duplicate attempt');
    }

    public function test_completed_booking_cannot_be_cancelled_or_have_history_overwritten(): void
    {
        $booking = Booking::factory()->create(['status' => 'completed']);
        try {
            app(BookingCancellationService::class)->cancel($booking, null, 'Invalid late cancellation');
            $this->fail('A completed stay cannot be cancelled.');
        } catch (ValidationException) {
            $this->assertSame('completed', $booking->fresh()->status);
            $this->assertSame(0, $booking->statusHistory()->where('to_status', 'cancelled')->count());
        }
    }
}
