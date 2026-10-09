<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePageReadReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_marks_only_current_page_property_messages_as_read(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $guest->id, 'property_id' => $property->id]);
        $thread = BookingConversation::query()->create(['booking_id' => $booking->id, 'property_id' => $property->id]);
        for ($i = 1; $i <= 45; $i++) {
            BookingMessage::query()->create([
                'conversation_id' => $thread->id,
                'sender_type' => 'property',
                'body' => 'Property message '.$i,
            ]);
        }

        $url = route('user.bookings.phase2.messages', $booking->reference);
        $this->actingAs($guest)->get($url)->assertOk();
        $this->assertSame(40, $thread->messages()->whereNotNull('read_at')->count());
        $this->assertSame(5, $thread->messages()->whereNull('read_at')->count());

        $this->actingAs($guest)->get($url.'?page=2')->assertOk();
        $this->assertSame(45, $thread->messages()->whereNotNull('read_at')->count());
    }

    public function test_owner_marks_only_current_page_guest_messages_as_read(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id]);
        $thread = BookingConversation::query()->create(['booking_id' => $booking->id, 'property_id' => $property->id]);
        for ($i = 1; $i <= 45; $i++) {
            BookingMessage::query()->create([
                'conversation_id' => $thread->id,
                'sender_type' => 'guest',
                'body' => 'Guest message '.$i,
            ]);
        }

        $url = route('user.owner.phase2.messages.show', [$property, $thread]);
        $this->actingAs($owner)->get($url)->assertOk();
        $this->assertSame(40, $thread->messages()->whereNotNull('read_at')->count());
        $this->assertSame(5, $thread->messages()->whereNull('read_at')->count());

        $this->actingAs($owner)->get($url.'?page=2')->assertOk();
        $this->assertSame(45, $thread->messages()->whereNotNull('read_at')->count());
    }
}
