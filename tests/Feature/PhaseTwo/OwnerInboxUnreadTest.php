<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerInboxUnreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_inbox_counts_only_unread_guest_messages(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id, 'user_id' => $guest->id,
        ]);
        $conversation = BookingConversation::query()->create([
            'property_id' => $property->id, 'booking_id' => $booking->id,
        ]);
        foreach ([
            ['sender_id' => $guest->id, 'sender_type' => 'guest', 'read_at' => null],
            ['sender_id' => $guest->id, 'sender_type' => 'guest', 'read_at' => now()],
            ['sender_id' => $owner->id, 'sender_type' => 'property', 'read_at' => null],
        ] as $sender) {
            BookingMessage::query()->create([
                'conversation_id' => $conversation->id,
                'body' => 'A secure reservation message',
                ...$sender,
            ]);
        }

        $this->actingAs($owner)->get(route('user.owner.phase2.messages', $property))
            ->assertOk()->assertSeeText('1 unread');
    }
}
