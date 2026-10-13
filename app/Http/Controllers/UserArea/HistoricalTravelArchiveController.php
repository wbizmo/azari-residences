<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use App\Models\DiningRequest;
use App\Models\TripAssembly;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Historical, read-only customer access. This controller must not resolve
 * supplier adapters, mutate reservations or initiate payment or fulfillment.
 */
final class HistoricalTravelArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = TravelRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['offer:id,title'])
            ->latest('created_at')
            ->paginate(20);

        $diningRequests = DiningRequest::query()
            ->where('user_id', $request->user()->id)
            ->with('partner:id,name')
            ->latest('created_at')->limit(20)->get();
        $assemblies = TripAssembly::query()
            ->where('user_id', $request->user()->id)
            ->with('itinerary:id,name')
            ->latest('created_at')->limit(20)->get();

        return response()->view('user.archive.travel-index', compact('requests', 'diningRequests', 'assemblies'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, TravelRequest $travelRequest): Response
    {
        // Check ownership before loading even sanitized audit events.
        abort_unless((int) $travelRequest->user_id === (int) $request->user()->id, 404);

        $travelRequest->load([
            'offer:id,title',
            'supplier:id,name',
            'events:id,travel_request_id,event_type,previous_status,new_status,created_at',
        ]);

        return response()->view('user.archive.travel-show', compact('travelRequest'))
            ->header('Cache-Control', 'no-store, private');
    }
}
