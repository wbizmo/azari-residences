<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PropertyListing;
use App\Models\PropertyListingControlDocument;
use App\Services\Owners\OwnerListingControlDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class OwnerListingControlDocumentController extends Controller
{
    public function download(
        Request $request,
        PropertyListing $listing,
        PropertyListingControlDocument $document,
        OwnerListingControlDocumentService $service
    ): BinaryFileResponse {
        abort_unless((int) $listing->user_id === (int) $request->user()->id, 404);
        abort_unless((int) $document->property_listing_id === (int) $listing->getKey(), 404);

        $response = response()->download($service->readablePath($document), $document->original_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // Symfony can mark file downloads public by default; override that
        // explicitly for legally sensitive ownership/management evidence.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
