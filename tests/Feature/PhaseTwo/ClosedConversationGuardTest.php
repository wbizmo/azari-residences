<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClosedConversationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_post_into_a_closed_booking_thread(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $conversation = BookingConversation::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'closed_at' => now(),
        ]);

        $this->actingAs($guest)
            ->post(route('user.bookings.phase2.messages.store', $booking->reference), [
                'client_token' => (string) Str::uuid(),
                'body' => 'This message must not be delivered.',
            ])->assertUnprocessable();

        $this->assertSame(0, $conversation->messages()->count());
    }
}
