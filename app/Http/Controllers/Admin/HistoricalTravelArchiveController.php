<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Read-only staff archive. The administrative travel supplier and payout
 * mutations remain disabled by the accommodation-only cutover middleware.
 */
final class HistoricalTravelArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = TravelRequest::query()
            ->with(['offer:id,title'])
            ->latest('created_at')->paginate(30);

        return response()->view('admin.archive.travel-index', compact('requests'))
            ->header('Cache-Control', 'no-store, private');
    }
}
