<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TripItinerary;
use App\Services\Travel\TravelRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TravelCatalogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(config('travel.requests_enabled'), 404);

        $kind = $request->validate([
            'kind' => ['sometimes', Rule::in(['transfer', 'experience', 'car', 'flight'])],
        ])['kind'] ?? null;

        $certifiedFlightKeys = app(\App\Services\Travel\TravelPartnerGateway::class)->certifiedKeys();

        $offers = TravelOffer::query()
            ->with(['supplier', 'slots' => fn ($q) => $q->where('starts_at', '>', now())])
            ->whereHas('supplier', fn ($q) => $q->where('status', 'approved')
                ->whereNotNull('contract_verified_at')
                ->whereNotNull('safety_verified_at')
                ->whereNotNull('approved_by'))
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($certifiedFlightKeys): void {
                $q->where('kind', '!=', 'flight')
                    ->orWhereHas('supplier',
                        fn ($supplier) => $supplier->whereIn('integration_key', $certifiedFlightKeys));
            })
            ->whereIn('currency', ['USD', 'EUR', 'GBP', 'NGN', 'CAD'])
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderBy('starts_at')->orderBy('id')
            ->paginate(12)->withQueryString();

        $requests = TravelRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['offer:id,title', 'itinerary:id,name'])
            ->latest()->limit(20)->get();

        $itineraries = TripItinerary::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')->limit(60)->get(['id', 'name']);

        $stays = $request->user()->bookings()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->latest()->limit(30)->get(['id', 'reference', 'trip_itinerary_id']);

        return view('user.travel.index', compact('kind', 'offers', 'requests', 'itineraries', 'stays'));
    }

    public function store(Request $request, TravelRequestService $service): RedirectResponse
    {
        abort_unless(config('travel.requests_enabled'), 404);

        $data = $request->validate([
            'offer_id' => ['required', 'integer', 'exists:travel_offers,id'],
            'slot_id' => ['nullable', 'integer', 'exists:travel_experience_slots,id'],
            'party_size' => ['required', 'integer', 'min:1', 'max:12'],
            'idempotency_key' => ['required', 'uuid'],
            'trip_itinerary_id' => ['nullable', 'integer'],
            'booking_id' => ['nullable', 'integer'],
            'bags' => ['nullable', 'integer', 'min:0', 'max:30'],
            'accessible' => ['nullable', 'boolean'],
            'flight_number' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9 -]+$/'],
            'data_share_consent' => ['nullable', 'boolean'],
        ]);
        $offer = TravelOffer::query()->findOrFail($data['offer_id']);
        $travelRequest = $service->create($request->user(), $offer, $data);

        return redirect()->route('user.travel.show', $travelRequest)
            ->with('success', 'Your travel request was recorded. It is not a confirmed booking and no payment was taken.');
    }

    public function show(Request $request, TravelRequest $travelRequest): View
    {
        abort_unless(config('travel.requests_enabled'), 404);
        abort_unless((int) $travelRequest->user_id === (int) $request->user()->id, 404);

        $travelRequest->load(['offer', 'supplier:id,name,support_email', 'itinerary:id,name', 'events:id,travel_request_id,event_type,new_status,created_at']);
        return view('user.travel.show', compact('travelRequest'));
    }

    public function cancel(Request $request, TravelRequest $travelRequest, TravelRequestService $service): RedirectResponse
    {
        abort_unless(config('travel.requests_enabled'), 404);
        $service->cancel($request->user(), $travelRequest);

        return redirect()->route('user.travel.show', $travelRequest)
            ->with('success', 'Travel request cancelled. Any linked accommodation remains unchanged.');
    }
}
