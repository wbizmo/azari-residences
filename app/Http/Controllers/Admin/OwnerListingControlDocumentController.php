<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PropertyListing;
use App\Models\PropertyListingControlDocument;
use App\Notifications\OwnerListingStatusNotification;
use App\Services\Owners\OwnerListingControlDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class OwnerListingControlDocumentController extends Controller
{
    public function download(
        PropertyListing $listing,
        PropertyListingControlDocument $document,
        OwnerListingControlDocumentService $service
    ): BinaryFileResponse {
        abort_unless((int) $document->property_listing_id === (int) $listing->id, 404);
        $path = $service->readablePath($document);

        return response()->download($path, $document->original_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function review(
        Request $request,
        PropertyListing $listing,
        PropertyListingControlDocument $document,
        OwnerListingControlDocumentService $service
    ): RedirectResponse {
        abort_unless((int) $document->property_listing_id === (int) $listing->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'review_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $listing, $document, $service, $data): void {
            $lockedListing = PropertyListing::query()->whereKey($listing->id)
                ->lockForUpdate()->firstOrFail();
            $locked = PropertyListingControlDocument::query()
                ->where('property_listing_id', $lockedListing->id)
                ->whereKey($document->id)->lockForUpdate()->firstOrFail();

            abort_unless(in_array($lockedListing->status, ['submitted', 'under_review', 'correction_requested'], true), 422,
                'This property is no longer in a reviewable state.');

            if ($locked->status === $data['status']) {
                return; // An unchanged reviewer retry must not duplicate audit actions.
            }

            abort_unless($locked->status === 'pending', 422,
                'This evidence has already been decided. Request another document from the owner.');

            $path = $service->readablePath($locked);
            if (! hash_equals($locked->sha256, hash_file('sha256', $path))) {
                throw ValidationException::withMessages([
                    'document' => 'The private file no longer matches its original upload. Request a new document.',
                ]);
            }

            $old = $locked->status;
            $locked->forceFill([
                'status' => $data['status'],
                'review_note' => $data['review_note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            AuditLog::record('owner_listing.control_document_reviewed', $locked,
                ['status' => $old], ['status' => $data['status']],
                ['listing_id' => $lockedListing->id]);
        }, 3);

        $message = $data['status'] === 'verified'
            ? 'Your property control document has been manually verified.'
            : 'Your proof of property control needs correction. Upload a replacement document.';
        try {
            $listing->user->notify(OwnerListingStatusNotification::forListing($listing->fresh(), $message));
        } catch (\Throwable $e) {
            Log::warning('Owner document review notification delivery failed', [
                'listing_id' => $listing->id, 'error' => $e->getMessage(),
            ]);
        }

        return back()->with('status', 'Private evidence reviewed and decision recorded.');
    }
}
