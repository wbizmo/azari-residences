<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\Property;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageDeliveryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_message_alerts_property_owner_once_on_retry(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);

        $payload = [
            'body' => 'Could you confirm the arrival instructions, please?',
            'client_token' => (string) Str::uuid(),
        ];
        $route = route('user.bookings.phase2.messages.store', $booking->reference);
        $this->actingAs($guest)->post($route, $payload)->assertRedirect();
        $this->actingAs($guest)->post($route, $payload)->assertRedirect();

        $this->assertDatabaseCount('booking_messages', 1);
        $this->assertCount(1, Notification::sent($owner, PremiumMailNotification::class)
            ->filter(fn ($notice) => $notice->template === 'booking-message'));
        $this->assertCount(0, Notification::sent($guest, PremiumMailNotification::class)
            ->filter(fn ($notice) => $notice->template === 'booking-message'));
    }

    public function test_property_reply_alerts_booking_guest_once_on_retry(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
        $conversation = BookingConversation::query()->create([
            'property_id' => $property->id,
            'booking_id' => $booking->id,
        ]);

        $payload = [
            'body' => 'Yes, check-in starts at the published arrival time.',
            'client_token' => (string) Str::uuid(),
        ];
        $route = route('user.owner.phase2.messages.store', [$property, $conversation]);
        $this->actingAs($owner)->post($route, $payload)->assertRedirect();
        $this->actingAs($owner)->post($route, $payload)->assertRedirect();

        $this->assertDatabaseCount('booking_messages', 1);
        $this->assertCount(1, Notification::sent($guest, PremiumMailNotification::class)
            ->filter(fn ($notice) => $notice->template === 'booking-message'));
        $this->assertCount(0, Notification::sent($owner, PremiumMailNotification::class)
            ->filter(fn ($notice) => $notice->template === 'booking-message'));
    }
}
