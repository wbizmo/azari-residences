<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BookingConversation;
use App\Models\BookingMessage;
use App\Models\BookingMessageAlertOutbox;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use App\Models\Property;
use App\Services\Owners\PropertyAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\BookingAttachmentName;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OwnerBookingMessageController extends Controller
{
    public function show(Request $request, Property $property, BookingConversation $conversation, PropertyAccessService $access): View
    {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id, 404);

        $messages = $conversation->messages()->oldest()->paginate(40);
        // A paginated conversation may have unseen later pages. Mark only
        // messages actually rendered to this recipient as read.
        $visibleIds = $messages->getCollection()->pluck('id')->all();
        if ($visibleIds !== []) {
            $conversation->messages()
                ->whereIn('id', $visibleIds)
                ->where('sender_type', 'guest')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return view('user.owner.conversation-show', compact('property', 'conversation', 'messages'));
    }

    public function store(Request $request, Property $property, BookingConversation $conversation, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $conversation->property_id === (int) $property->id && ! $conversation->closed_at, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'client_token' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $file = $request->file('attachment');
        // Validate content before committing any bytes to private storage.
        $safeAttachmentName = $file ? BookingAttachmentName::fromUpload($file) : null;
        $attachmentPath = $file?->store('booking-messages', 'private');

        try {
            $created = DB::transaction(function () use ($conversation, $request, $property, $data, $file, $attachmentPath, $safeAttachmentName, $access): bool {
                $locked = BookingConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->property_id === (int) $property->id && ! $locked->closed_at, 404);
                $access->assert($request->user(), $property, 'messages.manage');

                if ($locked->messages()
                    ->where('sender_id', $request->user()->id)
                    ->where('client_token', $data['client_token'])
                    ->exists()) {
                    return false;
                }

                $message = $locked->messages()->create([
                    'sender_id' => $request->user()->id,
                    'sender_type' => 'property',
                    'client_token' => $data['client_token'],
                    'body' => $data['body'],
                    'locale' => app()->getLocale(),
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $safeAttachmentName,
                    'attachment_scan_status' => $attachmentPath ? 'pending' : null,
                ]);

                $recipientId = Booking::query()
                    ->whereKey($locked->booking_id)
                    ->where('property_id', $property->getKey())
                    ->value('user_id');
                if ($recipientId && (int) $recipientId !== (int) $request->user()->id) {
                    BookingMessageAlertOutbox::query()->create([
                        'booking_message_id' => $message->id,
                        'booking_id' => $locked->booking_id,
                        'recipient_id' => $recipientId,
                    ]);
                }

                $locked->update(['last_message_at' => $message->created_at]);
                AuditLog::record('booking_message.property_sent', $locked, [], [
                    'message_id' => $message->id,
                    'property_id' => $property->id,
                ]);

                return true;
            }, 3);
        } catch (\Throwable $exception) {
            if ($attachmentPath) {
                Storage::disk('private')->delete($attachmentPath);
            }

            throw $exception;
        }

        if (! $created && $attachmentPath) {
            Storage::disk('private')->delete($attachmentPath);
        }

        return back()->with('success', $created ? 'Message sent.' : 'This message was already sent.');
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
        abort_unless($message->attachment_scan_status === 'clean', 404);
        abort_unless(Storage::disk('private')->exists($message->attachment_path), 404);

        AuditLog::record('booking_message.attachment_downloaded', $conversation, [], [
            'message_id' => $message->id,
            'property_id' => $property->id,
        ]);

        return Storage::disk('private')->download(
            $message->attachment_path,
            BookingAttachmentName::forDownload($message->attachment_name, $message->attachment_path)
        );
    }
}
