<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
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

    public function sendMessage(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->booking($request, $reference);
        abort_if(in_array($booking->status, ['cancelled', 'no_show'], true) && $booking->updated_at?->lt(now()->subDays(30)), 422);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $conversation = BookingConversation::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            ['property_id' => $booking->property_id]
        );

        $file = $request->file('attachment');
        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'sender_type' => 'guest',
            'body' => $data['body'],
            'locale' => app()->getLocale(),
            'attachment_path' => $file?->store('booking-messages', 'private'),
            'attachment_name' => $file?->getClientOriginalName(),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        AuditLog::record('booking_message.guest_sent', $booking, [], ['message_id' => $message->id]);

        return back()->with('success', 'Message sent.');
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

    public function subscribePush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4000'],
            'keys.p256dh' => ['required', 'string', 'max:1000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
        ]);

        DB::table('web_push_subscriptions')->updateOrInsert(
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint']],
            [
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
            ->where('endpoint', $data['endpoint'])
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
