<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\PropertyStaffMembership;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use App\Services\Owners\PropertyAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class BookingMessagingService
{
    public function __construct(private readonly PropertyAccessService $access) {}

    public function assertMessagingAllowed(Booking $booking): void
    {
        if (in_array($booking->status, ['draft', 'expired'], true)) {
            throw ValidationException::withMessages([
                'message' => 'Messaging becomes available after a booking is created.',
            ]);
        }

        if (in_array($booking->status, ['cancelled', 'no_show', 'checked_out', 'completed'], true)
            && $booking->updated_at?->lt(now()->subDays(30))) {
            throw ValidationException::withMessages([
                'message' => 'This booking conversation is outside the messaging retention window. Use support if you still need help.',
            ]);
        }
    }

    public function create(
        BookingConversation $conversation,
        Booking $booking,
        ?User $sender,
        string $senderType,
        string $body,
        string $clientToken,
        ?string $attachmentPath = null,
        ?string $attachmentName = null
    ): array {
        $this->assertMessagingAllowed($booking);

        $hash = hash('sha256', implode('|', [
            'booking-message-v1',
            $booking->id,
            $senderType,
            $sender?->id ?? 0,
            $clientToken,
        ]));

        $existing = BookingMessage::query()->where('client_token_hash', $hash)->first();
        if ($existing) {
            return [$existing, false];
        }

        try {
            $message = $conversation->messages()->create([
                'sender_id' => $sender?->id,
                'sender_type' => $senderType,
                'body' => $body,
                'locale' => app()->getLocale(),
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'client_token_hash' => $hash,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return [$message, true];
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            $message = BookingMessage::query()->where('client_token_hash', $hash)->firstOrFail();
            return [$message, false];
        }
    }

    public function notifyRecipients(Booking $booking, BookingConversation $conversation, BookingMessage $message): void
    {
        if ($message->notification_queued_at) {
            return;
        }

        $booking->loadMissing(['user', 'property.owner']);
        $dedupeKey = 'booking-message:'.$message->id;

        if ($message->sender_type === 'guest') {
            $recipientIds = PropertyStaffMembership::query()
                ->where('property_id', $booking->property_id)
                ->whereNull('revoked_at')
                ->whereNotNull('accepted_at')
                ->pluck('user_id')
                ->push($booking->property?->owner_id)
                ->filter()
                ->unique();

            $recipients = User::query()->whereIn('id', $recipientIds)->get()
                ->filter(fn (User $user) => $booking->property && $this->access->can($user, $booking->property, 'messages.manage'));

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PremiumMailNotification(
                    'booking-guest-message',
                    'New guest message for '.$booking->reference,
                    ['A guest sent a new message linked to this booking. Sign in to reply securely.'],
                    'Open conversation',
                    route('user.owner.phase2.messages.show', [$booking->property, $conversation]),
                    [
                        'booking_id' => $booking->id,
                        'dedupe_key' => $dedupeKey,
                        'classification' => 'transactional',
                    ]
                ));
            }
        } elseif ($booking->user) {
            $booking->user->notify(new PremiumMailNotification(
                'booking-property-message',
                'New property message for '.$booking->reference,
                ['The property team sent a new message linked to your stay. Sign in to read it securely.'],
                'Open conversation',
                route('user.bookings.phase2.messages', $booking->reference),
                [
                    'booking_id' => $booking->id,
                    'dedupe_key' => $dedupeKey,
                    'classification' => 'transactional',
                ]
            ));
        }

        $message->forceFill(['notification_queued_at' => now()])->save();
    }
}
