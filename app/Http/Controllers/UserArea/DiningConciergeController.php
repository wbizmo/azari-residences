<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\DiningPartner;
use App\Models\DiningRequest;
use App\Models\TripItinerary;
use App\Services\Travel\DiningConciergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DiningConciergeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(config('travel.dining_enabled', false),404);
        $filter = $request->validate(['city'=>['nullable','string','max:100'],'near_stay'=>['nullable','integer']]);
        $nearStay = null;
        if (! empty($filter['near_stay'])) {
            $nearStay = \App\Models\Booking::query()->whereKey($filter['near_stay'])
                ->where('user_id',$request->user()->id)->firstOrFail();
            abort_unless($nearStay->property_latitude !== null
                && $nearStay->property_longitude !== null,422,'This stay has no verified location.');
        }
        $lat = $nearStay ? (float)$nearStay->property_latitude : null;
        $lon = $nearStay ? (float)$nearStay->property_longitude : null;
        $latRadius=30/111.2;
        $lonRadius=$lat===null?null:min(180,30/(111.2*max(0.1,cos(deg2rad($lat)))));
        $partners = DiningPartner::query()->where('status','published')
            ->where('details_verified_at','>=',now()->subDays(90))
            ->when($lat!==null,fn($q)=>$q->whereBetween('latitude',[$lat-$latRadius,$lat+$latRadius])
                ->whereBetween('longitude',[$lon-$lonRadius,$lon+$lonRadius]))
            ->when(filled($filter['city']??null), fn($q)=>$q->where('city','like',
                '%'.str_replace(['%','_'],['\%','\_'],trim($filter['city'])).'%'))
            ->orderBy('name')->paginate(12);
        $itineraries = TripItinerary::query()->where('user_id',$request->user()->id)
            ->orderBy('name')->limit(100)->get(['id','name']);
        $diningRequests = DiningRequest::query()->where('user_id',$request->user()->id)
            ->with('partner:id,name,timezone')->latest()->limit(30)->get();
        $stays = $request->user()->bookings()->whereNotNull('property_latitude')
            ->whereNotNull('property_longitude')->latest()->limit(30)
            ->get(['id','reference']);
        return view('user.dining.index', compact('partners','itineraries','diningRequests','stays'));
    }

    public function store(Request $request, DiningConciergeService $service): RedirectResponse
    {
        abort_unless(config('travel.dining_enabled',false),404);
        $input=$request->validate([
            'dining_partner_id'=>['required','integer','exists:dining_partners,id'],
            'party_size'=>['required','integer','min:1','max:12'],
            'requested_for'=>['required','date','after:now'],
            'idempotency_key'=>['required','uuid'],
            'trip_itinerary_id'=>['nullable','integer'],
            'booking_id'=>['nullable','integer'],
            'dietary_notes'=>['nullable','string','max:750'],
            'supplier_share_consent'=>['nullable','boolean'],
        ]);
        $service->create($request->user(),DiningPartner::query()->findOrFail($input['dining_partner_id']),$input);
        return back()->with('success','Concierge request recorded. No table has been reserved or charged.');
    }

    public function cancel(Request $request,DiningRequest $diningRequest,DiningConciergeService $service): RedirectResponse
    {
        abort_unless(config('travel.dining_enabled',false),404);
        $result=$service->cancel($request->user(),$diningRequest);
        return back()->with('success',$result->status==='cancellation_requested'
            ? 'Cancellation sent for staff follow-up; provider confirmation is outstanding.'
            : 'Your pending concierge request was cancelled; your stay was not changed.');
    }
}
