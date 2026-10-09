<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\Booking;
use App\Models\Property;
use App\Services\Owners\PropertyAccessService;
use App\Services\Bookings\BookingMessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OwnerBookingMessageController extends Controller
{
    public function show(Request $request, Property $property, BookingConversation $conversation, PropertyAccessService $access): View
    {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id, 404);

        $messages = $conversation->messages()->oldest()->paginate(40);
        $conversation->messages()->where('sender_type', 'guest')->whereNull('read_at')->update(['read_at' => now()]);

        return view('user.owner.conversation-show', compact('property', 'conversation', 'messages'));
    }

    public function store(
        Request $request,
        Property $property,
        BookingConversation $conversation,
        PropertyAccessService $access,
        BookingMessagingService $messaging
    ): RedirectResponse {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id && ! $conversation->closed_at, 404);

        $booking = Booking::query()->whereKey($conversation->booking_id)->where('property_id', $property->id)->firstOrFail();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'client_token' => ['required', 'string', 'max:128'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $file = $request->file('attachment');
        $attachmentPath = $file?->store('booking-messages', 'private');

        [$message, $created] = $messaging->create(
            $conversation,
            $booking,
            $request->user(),
            'property',
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
            AuditLog::record('booking_message.property_sent', $conversation, [], [
                'message_id' => $message->id,
                'property_id' => $property->id,
            ]);
        }

        return back()->with('success', $created ? 'Message sent.' : 'That message was already sent.');
    }

    public function poll(
        Request $request,
        Property $property,
        BookingConversation $conversation,
        PropertyAccessService $access,
        BookingMessagingService $messaging
    ) {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id, 404);

        $booking = Booking::query()->whereKey($conversation->booking_id)->where('property_id', $property->id)->firstOrFail();
        $messaging->assertMessagingAllowed($booking);
        $after = max(0, (int) $request->query('after', 0));

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->oldest('id')
            ->limit(50)
            ->get();

        $conversation->messages()->where('sender_type', 'guest')->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'body' => $message->body,
                'created_at' => $message->created_at->toIso8601String(),
                'attachment_url' => $message->attachment_path
                    ? route('user.owner.phase2.messages.attachment', [$property, $conversation, $message])
                    : null,
                'attachment_name' => $message->attachment_name,
            ])->values(),
            'latest_id' => (int) ($messages->max('id') ?: $after),
        ]);
    }

    public function attachment(
        Request $request,
        Property $property,
        BookingConversation $conversation,
        BookingMessage $message,
        PropertyAccessService $access
    ) {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id, 404);
        abort_unless((int) $message->conversation_id === (int) $conversation->id && $message->attachment_path, 404);
        abort_unless(Storage::disk('private')->exists($message->attachment_path), 404);

        AuditLog::record('booking_message.attachment_downloaded', $conversation, [], [
            'message_id' => $message->id,
            'property_id' => $property->id,
        ]);

        return Storage::disk('private')->download(
            $message->attachment_path,
            $message->attachment_name ?: 'attachment'
        );
    }
}
