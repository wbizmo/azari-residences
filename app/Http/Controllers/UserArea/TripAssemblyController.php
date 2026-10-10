<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\TripItinerary;
use App\Services\Travel\TripAssemblyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TripAssemblyController extends Controller
{
    public function store(Request $request, TripItinerary $itinerary, TripAssemblyService $service): RedirectResponse
    {
        abort_unless(config('travel.trip_assembly_enabled',false),404);
        $data=$request->validate(['idempotency_key'=>['required','uuid']]);
        $service->prepare($request->user(),$itinerary,$data['idempotency_key']);
        return back()->with('success','Trip review snapshot created. Every item retains separate provider confirmation and payment.');
    }
}
