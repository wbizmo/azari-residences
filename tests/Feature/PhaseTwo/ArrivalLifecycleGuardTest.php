<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrivalLifecycleGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_booking_cannot_rewrite_arrival_details(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->id, 'status' => 'cancelled',
            'arrival_notes' => 'Previously arranged',
        ]);
        $this->actingAs($guest)
            ->patch(route('user.bookings.phase2.arrival.update', $booking->reference), [
                'arrival_notes' => 'New instructions after cancellation',
                'arrival_time' => '17:30',
            ])->assertUnprocessable();

        $this->assertSame('Previously arranged', $booking->fresh()->arrival_notes);
    }

    public function test_other_guest_cannot_update_arrival_details(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
        $this->actingAs($guest)
            ->patch(route('user.bookings.phase2.arrival.update', $booking->reference), [
                'arrival_notes' => 'Unauthorized change',
            ])->assertForbidden();
    }
}
