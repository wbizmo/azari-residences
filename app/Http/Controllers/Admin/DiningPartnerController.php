<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DiningPartner;
use App\Models\DiningRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DiningPartnerController extends Controller
{
    public function index(): View
    {
        return view('admin.dining.index', [
            'partners'=>DiningPartner::query()->latest()->limit(100)->get(),
            'enquiries'=>DiningRequest::query()->with('partner:id,name')
                ->whereIn('status',['pending_concierge','cancellation_requested'])
                ->orderBy('requested_for')->limit(100)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate([
            'name'=>['required','string','min:2','max:160'],
            'address'=>['required','string','max:350'],
            'city'=>['required','string','max:100'],
            'timezone'=>['required','timezone'],
            'website'=>['nullable','url','starts_with:https://','max:500'],
            'support_email'=>['nullable','email','max:180'],
            'disclosures'=>['required','string','max:1500'],
            'dietary_options'=>['nullable','array','max:15'],
            'dietary_options.*'=>['string','max:100'],
            'accessibility'=>['nullable','array','max:15'],
            'accessibility.*'=>['string','max:100'],
        ]);
        $partner=DiningPartner::query()->create($data+['status'=>'pending_review']);
        AuditLog::record('dining.partner_created',$partner,[],['status'=>'pending_review']);
        return back()->with('success','Dining partner drafted. Verify details before publication.');
    }

    public function publish(Request $request,DiningPartner $partner): RedirectResponse
    {
        $request->validate(['review_attestation'=>['required',Rule::in(['DETAILS_AND_TERMS_VERIFIED'])]]);
        DB::transaction(function () use ($partner,$request) {
            $locked=DiningPartner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status'=>'published','details_verified_at'=>now(),
                'reviewed_by'=>$request->user()->id]);
            AuditLog::record('dining.partner_published',$locked,[],['status'=>'published']);
        },3);
        return back()->with('success','Verified dining information published, not live reservation availability.');
    }

    public function pause(DiningPartner $partner): RedirectResponse
    {
        DB::transaction(function () use ($partner) {
            $locked=DiningPartner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status'=>'paused']);
            AuditLog::record('dining.partner_paused',$locked,[],['status'=>'paused']);
        },3);
        return back()->with('success','Dining listing unpublished for new enquiries.');
    }

    public function verifyReservation(Request $request,DiningRequest $diningRequest,\App\Services\Travel\DiningReservationVerificationService $service): RedirectResponse
    {
        $data=$request->validate(['provider_reference'=>['required','string','min:5','max:160',
            'regex:/^[A-Za-z0-9_.:-]+$/D']]);
        $result=$service->verify($request->user(),$diningRequest,$data['provider_reference']);
        return back()->with('success','Partner evidence verified: '.$result->status.'. No accommodation charge was changed.');
    }

    public function decline(DiningRequest $diningRequest): RedirectResponse
    {
        DB::transaction(function () use ($diningRequest) {
            $locked=DiningRequest::query()->whereKey($diningRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'pending_concierge') $locked->update(['status'=>'unavailable']);
            if ($locked->status === 'cancellation_requested') $locked->update(['status'=>'support_required']);
        },3);
        return back()->with('success','Concierge update recorded. No table confirmation or refund issued.');
    }
}
