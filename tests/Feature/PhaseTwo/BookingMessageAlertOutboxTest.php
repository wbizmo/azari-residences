<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\BookingMessageAlertOutbox;
use App\Models\Property;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingMessageAlertOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_interrupted_delivery_lease_can_be_reclaimed_without_duplicate_alerts(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
        $thread = BookingConversation::query()->create([
            'property_id' => $property->id, 'booking_id' => $booking->id,
        ]);
        $message = BookingMessage::query()->create([
            'conversation_id' => $thread->id, 'sender_id' => $guest->id,
            'sender_type' => 'guest', 'client_token' => (string) Str::uuid(),
            'body' => 'Please confirm the arrival time.',
        ]);
        $outbox = BookingMessageAlertOutbox::query()->create([
            'booking_message_id' => $message->id,
            'booking_id' => $booking->id, 'recipient_id' => $owner->id,
            'state' => 'processing', 'attempts' => 1,
            'claimed_at' => now()->subMinutes(30),
        ]);

        $this->artisan('resavar:deliver-booking-message-alerts --limit=1')->assertExitCode(0);

        $this->assertSame('queued', $outbox->fresh()->state);
        $this->assertSame(2, $outbox->fresh()->attempts);
        Notification::assertSentToTimes($owner, PremiumMailNotification::class, 1);

        $this->artisan('resavar:deliver-booking-message-alerts --limit=1')->assertExitCode(0);
        Notification::assertSentToTimes($owner, PremiumMailNotification::class, 1);
    }

    public function test_not_yet_due_retry_is_not_dispatched(): void
    {
        Notification::fake();
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $thread = BookingConversation::query()->create([
            'property_id' => $booking->property_id, 'booking_id' => $booking->id,
        ]);
        $message = BookingMessage::query()->create([
            'conversation_id' => $thread->id, 'sender_id' => $guest->id,
            'sender_type' => 'guest', 'client_token' => (string) Str::uuid(),
            'body' => 'This alert is scheduled for later.',
        ]);
        $outbox = BookingMessageAlertOutbox::query()->create([
            'booking_message_id' => $message->id,
            'booking_id' => $booking->id, 'recipient_id' => $guest->id,
            'state' => 'retry', 'next_attempt_at' => now()->addHour(),
        ]);

        $this->artisan('resavar:deliver-booking-message-alerts')->assertExitCode(0);
        $this->assertSame('retry', $outbox->fresh()->state);
        $this->assertCount(0, Notification::sent($guest, PremiumMailNotification::class)
            ->filter(fn ($notice) => $notice->template === 'booking-message'));
    }
}
