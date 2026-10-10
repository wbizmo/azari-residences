<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationPollingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_polls_own_booking_only(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id]);

        $url = route('user.bookings.phase2.messages.poll', $booking->reference);
        $this->actingAs($guest)->getJson($url)->assertOk()
            ->assertJsonPath('latest_id', 0)->assertJsonPath('last_page', 1);
        $this->actingAs($other)->getJson($url)->assertForbidden();
    }

    public function test_property_poll_checks_current_owner_permission(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id]);
        $conversation = BookingConversation::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $property->id,
        ]);
        $url = route('user.owner.phase2.messages.poll', [$property, $conversation]);

        $this->actingAs($owner)->getJson($url)->assertOk()
            ->assertJsonPath('latest_id', 0);
        $this->actingAs($other)->getJson($url)->assertNotFound();
    }
}
