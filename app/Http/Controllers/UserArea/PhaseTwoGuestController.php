<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\StayLifecycleEvent;
use App\Services\Bookings\BookingMessagingService;
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

        $conversation = BookingConversation::query()->firstOrCreate(
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

    public function sendMessage(
        Request $request,
        string $reference,
        BookingMessagingService $messaging
    ): RedirectResponse {
        $booking = $this->booking($request, $reference);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'client_token' => ['required', 'string', 'max:128'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $conversation = BookingConversation::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            ['property_id' => $booking->property_id]
        );

        $file = $request->file('attachment');
        $attachmentPath = $file?->store('booking-messages', 'private');

        [$message, $created] = $messaging->create(
            $conversation,
            $booking,
            $request->user(),
            'guest',
            $data['body'],
            $data['client_token'],
            $attachmentPath,
            $file?->getClientOriginalName(),
        );

        if (! $created && $attachmentPath) {
            Storage::disk('private')->delete($attachmentPath);
        }

        if ($created) {
            $messaging->notifyRecipients($booking, $conversation, $message);
            AuditLog::record('booking_message.guest_sent', $booking, [], ['message_id' => $message->id]);
        }

        return back()->with('success', $created ? 'Message sent.' : 'That message was already sent.');
    }

    public function pollMessages(Request $request, string $reference, BookingMessagingService $messaging): JsonResponse
    {
        $booking = $this->booking($request, $reference);
        $messaging->assertMessagingAllowed($booking);
        $after = max(0, (int) $request->query('after', 0));

        $conversation = BookingConversation::query()->where('booking_id', $booking->id)->first();
        if (! $conversation) {
            return response()->json(['messages' => [], 'latest_id' => $after]);
        }

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->oldest('id')
            ->limit(50)
            ->get();

        $conversation->messages()
            ->where('sender_type', 'property')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
                'attachment_url' => $message->attachment_path
                    ? route('user.bookings.phase2.messages.attachment', [$booking->reference, $message])
                    : null,
                'attachment_name' => $message->attachment_name,
            ])->values(),
            'latest_id' => (int) ($messages->max('id') ?: $after),
        ]);
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

        $booking->update($data);
        AuditLog::record('booking.arrival_updated', $booking, [], $data);

        return back()->with('success', 'Arrival details updated.');
    }

    public function selfCheckIn(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->booking($request, $reference);
        abort_unless($booking->isSelfCheckInEligible(), 422);

        DB::transaction(function () use ($booking, $request): void {
            $locked = $request->user()->bookings()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isSelfCheckInEligible(), 422);

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

    public function subscribePush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4000'],
            'keys.p256dh' => ['required', 'string', 'max:1000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
        ]);

        $endpointHash = hash('sha256', $data['endpoint']);

        DB::table('web_push_subscriptions')->updateOrInsert(
            ['user_id' => $request->user()->id, 'endpoint_hash' => $endpointHash],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'revoked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:4000']]);

        DB::table('web_push_subscriptions')
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
