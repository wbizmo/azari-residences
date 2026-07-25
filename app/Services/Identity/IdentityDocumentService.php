<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingIdentityLink;
use App\Models\GuestIdentityDocument;
use App\Models\IdentityAuditHistory;
use App\Models\IdentityType;
use App\Models\User;
use App\Models\UserIdentityDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IdentityDocumentService
{
    public function replaceUserIdentity(User $user, IdentityType $type, UploadedFile $file): UserIdentityDocument
    {
        return DB::transaction(function () use ($user, $type, $file): UserIdentityDocument {
            $current = $user->currentIdentity()->lockForUpdate()->first();
            $path = $file->store("identities/users/{$user->id}", 'private');
            $document = UserIdentityDocument::query()->create([
                'user_id' => $user->id,
                'identity_type_id' => $type->id,
                'document_type' => $type->slug,
                'disk' => 'private',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'is_current' => true,
                'replaces_id' => $current?->id,
                'review_status' => 'pending',
            ]);
            if ($current) $current->update(['is_current' => false, 'replaced_at' => now()]);

            IdentityAuditHistory::query()->create([
                'actor_id' => auth()->id() ?: $user->id,
                'document_type' => 'user',
                'document_id' => $document->id,
                'action' => $current ? 'replaced' : 'uploaded',
                'metadata' => ['identity_type' => $type->slug, 'previous_document_id' => $current?->id],
            ]);
            AuditLog::record('identity.user_document_saved', $document, [], ['identity_type' => $type->slug]);
            return $document;
        }, 3);
    }

    public function attachOwnerIdentity(Booking $booking, User $user, ?BookingGuest $leadGuest = null): ?BookingIdentityLink
    {
        $document = $user->currentIdentity()->first();
        if (! $document) return null;
        $leadGuest ??= $booking->guests()->where('is_lead', true)->first();
        if (! $leadGuest) return null;

        return BookingIdentityLink::query()->updateOrCreate(
            ['booking_id' => $booking->id, 'booking_guest_id' => $leadGuest->id],
            ['user_identity_document_id' => $document->id, 'guest_identity_document_id' => null, 'is_booking_owner' => true, 'linked_by' => $user->id],
        );
    }

    public function storeGuestIdentity(Booking $booking, BookingGuest $guest, string $documentType, UploadedFile $file, ?int $actorId = null): GuestIdentityDocument
    {
        abort_unless($guest->booking_id === $booking->id && $guest->type === 'adult', 404);

        return DB::transaction(function () use ($booking, $guest, $documentType, $file, $actorId): GuestIdentityDocument {
            $existing = $guest->identityDocument()->first();
            if ($existing && Storage::disk($existing->disk)->exists($existing->path)) {
                Storage::disk($existing->disk)->delete($existing->path);
            }
            $path = $file->store("identities/bookings/{$booking->reference}", 'private');
            $document = GuestIdentityDocument::query()->updateOrCreate(
                ['booking_guest_id' => $guest->id],
                [
                    'document_type' => $documentType,
                    'disk' => 'private',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'review_status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ],
            );
            BookingIdentityLink::query()->updateOrCreate(
                ['booking_id' => $booking->id, 'booking_guest_id' => $guest->id],
                ['guest_identity_document_id' => $document->id, 'user_identity_document_id' => null, 'is_booking_owner' => $guest->is_lead, 'linked_by' => $actorId],
            );
            IdentityAuditHistory::query()->create([
                'actor_id' => $actorId,
                'document_type' => 'booking_guest',
                'document_id' => $document->id,
                'booking_id' => $booking->id,
                'action' => $existing ? 'replaced' : 'uploaded',
                'metadata' => ['guest_id' => $guest->id, 'document_type' => $documentType],
            ]);
            AuditLog::record('identity.booking_guest_document_saved', $document, [], ['booking_reference' => $booking->reference], actorId: $actorId);
            return $document;
        }, 3);
    }
}
