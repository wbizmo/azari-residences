<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Notifications\PremiumMailNotification;
use App\Models\StayLifecycleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PhaseTwoGuestController extends Controller
{
    private function booking(Request $request, string $reference)
    {
        $booking = $request->user()->bookings()
            ->with(['property', 'guests'])
            ->where('reference', $reference)
            ->first();

        abort_unless($booking, 403);

        return $booking;
    }

    public function messages(Request $request, string $reference): View
    {
        $booking = $this->booking($request, $reference);

        $conversation = BookingConversation::query()->createOrFirst(
            ['booking_id' => $booking->id],
            ['property_id' => $booking->property_id]
        );

        $messages = $conversation->messages()->oldest()->paginate(40);
        $conversation->messages()
            ->where('sender_type', 'property')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('user.bookings.messages', compact('booking', 'conversation', 'messages'));
    }

    public function sendMessage(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->booking($request, $reference);
        abort_if(in_array($booking->status, ['cancelled', 'no_show'], true) && $booking->updated_at?->lt(now()->subDays(30)), 422);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'client_token' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $conversation = BookingConversation::query()->createOrFirst(
            ['booking_id' => $booking->id],
            ['property_id' => $booking->property_id]
        );

        abort_if($conversation->closed_at, 422, 'This booking conversation is closed.');

        $file = $request->file('attachment');
        $attachmentPath = $file?->store('booking-messages', 'private');

        try {
            $created = DB::transaction(function () use ($conversation, $request, $data, $file, $attachmentPath): bool {
                $locked = BookingConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->closed_at, 422, 'This booking conversation is closed.');

                // A browser retry or two concurrent tabs must not send a duplicate.
                if ($locked->messages()
                    ->where('sender_id', $request->user()->id)
                    ->where('client_token', $data['client_token'])
                    ->exists()) {
                    return false;
                }

                $message = $locked->messages()->create([
                    'sender_id' => $request->user()->id,
                    'sender_type' => 'guest',
                    'client_token' => $data['client_token'],
                    'body' => $data['body'],
                    'locale' => app()->getLocale(),
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $file?->getClientOriginalName(),
                ]);

                $locked->update(['last_message_at' => $message->created_at]);
                AuditLog::record('booking_message.guest_sent', $locked, [], ['message_id' => $message->id]);

                return true;
            }, 3);
        } catch (\Throwable $exception) {
            if ($attachmentPath) {
                Storage::disk('private')->delete($attachmentPath);
            }

            throw $exception;
        }

        // An attempted replay may carry a different upload. Never retain it.
        if (! $created && $attachmentPath) {
            Storage::disk('private')->delete($attachmentPath);
        }

        if ($created) {
            // The notification is queued and sent only for a new message.
            // Keep its content generic; do not include guest PII or attachments.
            $recipient = $booking->property?->owner;
            if ($recipient && (int) $recipient->id !== (int) $request->user()->id) {
                try {
                    $recipient->notify(new PremiumMailNotification(
                        'booking-message',
                        'A guest sent a message about a Resavar reservation',
                        ['A new message is waiting in your property inbox. Sign in to read it securely.'],
                        'Open conversation',
                        route('user.owner.phase2.messages', ['property' => $booking->property_id]),
                        ['booking_id' => $booking->id]
                    ));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        return back()->with('success', $created ? 'Message sent.' : 'This message was already sent.');
    }

    public function messageAttachment(Request $request, string $reference, BookingMessage $message)
    {
        $booking = $this->booking($request, $reference);
        $conversation = BookingConversation::query()->where('booking_id', $booking->id)->firstOrFail();
        abort_unless((int) $message->conversation_id === (int) $conversation->id && $message->attachment_path, 404);
        abort_unless(Storage::disk('private')->exists($message->attachment_path), 404);

        return Storage::disk('private')->download($message->attachment_path, $message->attachment_name ?: 'attachment');
    }

    public function arrival(Request $request, string $reference): View
    {
        $booking = $this->booking($request, $reference);
        $booking->load(['ratePlan.paymentPolicy', 'payments']);

        return view('user.bookings.arrival', compact('booking'));
    }

    public function updateArrival(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->booking($request, $reference);

        $data = $request->validate([
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'arrival_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $booking, $data): void {
            // Arrival instructions cannot be changed after the stay has
            // ended or been cancelled, including by a stale browser tab.
            $locked = $request->user()->bookings()
                ->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_if(
                in_array($locked->status, ['cancelled', 'no_show', 'checked_out', 'completed'], true)
                || filled($locked->checked_out_at) || filled($locked->completed_at),
                422,
                'Arrival details cannot be changed after a stay ends or is cancelled.'
            );

            $before = $locked->only(['arrival_time', 'arrival_notes']);
            $locked->update($data);
            AuditLog::record('booking.arrival_updated', $locked, $before, $data);
        }, 3);

        return back()->with('success', 'Arrival details updated.');
    }

    public function selfCheckIn(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->booking($request, $reference);
        abort_unless($booking->isCheckInEligible(), 422);

        DB::transaction(function () use ($booking, $request): void {
            $locked = $request->user()->bookings()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isCheckInEligible(), 422);

            $locked->update(['checked_in_at' => now(), 'status' => 'checked_in']);
            StayLifecycleEvent::query()->create([
                'booking_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event' => 'checked_in',
                'viewer_timezone' => $request->user()->timezone,
                'operational_timezone' => $locked->property_timezone ?: config('localization.platform_timezone', 'UTC'),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        }, 3);

        return back()->with('success', 'Check-in completed.');
    }

}
