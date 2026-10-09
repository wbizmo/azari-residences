<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseTwoCriticalFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_invitation_is_single_use_and_bound_to_verified_invitee(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $attacker = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $token = Str::random(64);
        DB::table('property_staff_invitations')->insert([
            'property_id' => $property->id, 'email' => $invitee->email,
            'role' => 'inventory_editor', 'token_hash' => hash('sha256', $token),
            'invited_by' => $owner->id, 'expires_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $route = route('user.owner.staff.accept', ['token' => $token]);
        $this->actingAs($attacker)->get($route)->assertForbidden();
        $this->actingAs($invitee)->get($route)->assertRedirect();
        $this->actingAs($invitee)->get($route)->assertNotFound();
        $this->assertDatabaseCount('property_staff_memberships', 1);
        $this->assertDatabaseHas('property_staff_memberships', [
            'property_id' => $property->id, 'user_id' => $invitee->id,
            'role' => 'inventory_editor',
        ]);
    }

    public function test_guest_message_retry_creates_one_row(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $token = (string) Str::uuid();
        $route = route('user.bookings.phase2.messages.store', $booking->reference);

        $this->actingAs($guest)->from($route)->post($route, [
            'body' => 'The guest will arrive later than expected.',
            'client_token' => $token,
        ])->assertRedirect();

        $this->actingAs($guest)->from($route)->post($route, [
            'body' => 'The guest will arrive later than expected.',
            'client_token' => $token,
        ])->assertRedirect();

        $this->assertDatabaseCount('booking_messages', 1);
        $this->assertDatabaseHas('booking_messages', [
            'sender_id' => $guest->id, 'client_token' => $token,
        ]);
    }

    public function test_owner_message_retry_deduplicates_and_other_properties_cannot_access(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id]);
        $conversation = BookingConversation::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $property->id,
        ]);
        $token = (string) Str::uuid();
        $route = route('user.owner.phase2.messages.store', [$property, $conversation]);
        $data = ['body' => 'Your key pickup instructions are available.', 'client_token' => $token];

        $this->actingAs($outsider)->post($route, $data)->assertNotFound();
        $this->actingAs($owner)->post($route, $data)->assertRedirect();
        $this->actingAs($owner)->post($route, $data)->assertRedirect();
        $this->assertDatabaseCount('booking_messages', 1);
        $this->assertDatabaseHas('booking_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'client_token' => $token,
        ]);
    }

    public function test_guest_cannot_send_message_to_another_guests_booking(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $intruder = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->post(route('user.bookings.phase2.messages.store', $booking->reference), [
            'body' => 'I should not be able to see the reservation.',
            'client_token' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertDatabaseCount('booking_messages', 0);
    }
}
