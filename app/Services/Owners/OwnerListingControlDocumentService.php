<?php

namespace App\Services\Owners;

use App\Models\PropertyListing;
use App\Models\PropertyListingControlDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Private legal evidence never shares the public property image filesystem.
 * Append-only files remain available for audit even after rejection/reupload.
 */
final class OwnerListingControlDocumentService
{
    public function append(PropertyListing $listing, UploadedFile $file): PropertyListingControlDocument
    {
        $path = $file->store('owner-control/'.$listing->getKey(), 'private');
        if (! $path) {
            throw ValidationException::withMessages([
                'proof_of_control' => 'The control document could not be stored. Try uploading it again.',
            ]);
        }

        try {
            $sha = hash_file('sha256', $file->getRealPath());
            if (! $sha) {
                throw new \RuntimeException('Unable to verify control document hash.');
            }

            // A new proof must be reviewed. Prior records remain in the
            // historical audit trail and no longer authorize publication.
            $listing->controlDocuments()->where('status', 'verified')
                ->update(['status' => 'superseded', 'updated_at' => now()]);

            return $listing->controlDocuments()->create([
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'sha256' => $sha,
                'status' => 'pending',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
    }

    public function readablePath(PropertyListingControlDocument $document): string
    {
        $path = $document->path;
        if (! is_string($path) || ! str_starts_with($path, 'owner-control/')
            || str_contains($path, '..') || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->path($path);
    }
}
