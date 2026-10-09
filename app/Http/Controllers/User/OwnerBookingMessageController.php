<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\Property;
use App\Services\Owners\PropertyAccessService;
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

    public function store(Request $request, Property $property, BookingConversation $conversation, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id && ! $conversation->closed_at, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $file = $request->file('attachment');
        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'sender_type' => 'property',
            'body' => $data['body'],
            'locale' => app()->getLocale(),
            'attachment_path' => $file?->store('booking-messages', 'private'),
            'attachment_name' => $file?->getClientOriginalName(),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        AuditLog::record('booking_message.property_sent', $conversation, [], ['message_id' => $message->id, 'property_id' => $property->id]);

        return back()->with('success', 'Message sent.');
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
