<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use App\Models\DiningRequest;
use App\Models\TripAssembly;
use App\Models\ChannelReservation;
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
            ->latest('created_at')->paginate(30, ['*'], 'requests_page')->withQueryString();

        $diningRequests = DiningRequest::query()
            ->with('partner:id,name')
            ->latest('created_at')->paginate(30, ['*'], 'dining_page')->withQueryString();
        $assemblies = TripAssembly::query()
            ->latest('created_at')->paginate(30, ['*'], 'assemblies_page')->withQueryString();

        $channelReservations = ChannelReservation::query()
            ->with('property:id,name')
            ->orderByDesc('id')->paginate(30, ['*'], 'channels_page')->withQueryString();

        return response()->view('admin.archive.travel-index', compact('requests', 'diningRequests', 'assemblies', 'channelReservations'))
            ->header('Cache-Control', 'no-store, private');
    }
}
