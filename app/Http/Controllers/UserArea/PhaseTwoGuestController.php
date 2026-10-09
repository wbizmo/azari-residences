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

        $file = $request->file('attachment');
        $attachmentPath = $file?->store('booking-messages', 'private');

        try {
            $created = DB::transaction(function () use ($conversation, $request, $data, $file, $attachmentPath): bool {
                $locked = BookingConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

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

        $this->assertSecurePushEndpoint($data['endpoint']);
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

        $this->assertSecurePushEndpoint($data['endpoint']);

        DB::table('web_push_subscriptions')
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }
    private function assertSecurePushEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $hostname = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        // Never let a saved browser endpoint become an arbitrary intranet
        // or loopback fetch target for future queued web-push delivery.
        $forbidden = $scheme !== 'https'
            || $hostname === ''
            || isset($parts['user'], $parts['pass'])
            || isset($parts['port']) && (int) $parts['port'] !== 443
            || $hostname === 'localhost'
            || str_ends_with($hostname, '.localhost')
            || str_ends_with($hostname, '.local')
            || str_ends_with($hostname, '.internal')
            || str_ends_with($hostname, '.test');

        if (filter_var($hostname, FILTER_VALIDATE_IP)) {
            $forbidden = true;
        }

        abort_if($forbidden, 422, 'A secure browser push service endpoint is required.');
    }

}
