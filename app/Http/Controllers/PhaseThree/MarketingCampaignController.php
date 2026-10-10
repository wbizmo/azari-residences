<?php

namespace App\Http\Controllers\PhaseThree;

use App\Http\Controllers\Controller;
use App\Services\PhaseThree\LifecycleCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Staff-controlled approval and pause; campaigns never activate on creation. */
final class MarketingCampaignController extends Controller
{
    public function index(LifecycleCampaignService $marketing): JsonResponse
    {
        return response()->json(DB::table('marketing_campaigns')->orderByDesc('id')
            ->limit(50)->get(['id','name','template_key','subject','status','approved_at','starts_at','ends_at'])
            ->map(fn ($row) => [...(array) $row, 'metrics' => $marketing->metrics($row->id)]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'=>['required','string','max:120'],
            'subject'=>['required','string','max:180'],
            'template_key'=>['required','alpha_dash','max:100'],
            'starts_at'=>['nullable','date','after_or_equal:now'],
            'ends_at'=>['nullable','date','after:starts_at'],
        ]);
        DB::table('marketing_campaigns')->insert([
            'name'=>$data['name'], 'subject'=>$data['subject'],
            'template_key'=>$data['template_key'], 'status'=>'draft',
            'starts_at'=>$data['starts_at'] ?? null, 'ends_at'=>$data['ends_at'] ?? null,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return back()->with('success','Campaign drafted. Requires separate approval before any delivery can be reserved.');
    }

    public function approve(Request $request, int $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request,$campaign): void {
            $current=DB::table('marketing_campaigns')->where('id',$campaign)->lockForUpdate()->first();
            abort_unless($current && $current->status==='draft',409);
            DB::table('marketing_campaigns')->where('id',$campaign)->update([
                'status'=>'approved', 'approved_by'=>$request->user()->id,
                'approved_at'=>now(), 'updated_at'=>now(),
            ]);
        });
        return back()->with('success','Campaign approved. Recipient eligibility and consent remain required.');
    }

    public function pause(int $campaign): RedirectResponse
    {
        DB::transaction(function () use ($campaign): void {
            $current=DB::table('marketing_campaigns')->where('id',$campaign)->lockForUpdate()->first();
            abort_unless($current && in_array($current->status,['draft','approved'],true),409);
            DB::table('marketing_campaigns')->where('id',$campaign)->update([
                'status'=>'paused','updated_at'=>now(),
            ]);
            DB::table('marketing_deliveries')->where('marketing_campaign_id',$campaign)
                ->where('status','reserved')->update([
                    'status'=>'suppressed','suppressed_at'=>now(),'updated_at'=>now(),
                ]);
        });
        return back()->with('success','Campaign paused and pending deliveries suppressed.');
    }
}
