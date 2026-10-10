<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TripAssembly;
use App\Services\Travel\TripAssemblyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TripAssemblyRecoveryController extends Controller
{
    public function index(): View
    {
        return view('admin.travel.assemblies', [
            'assemblies'=>TripAssembly::query()->whereIn('status',[
                'review_required','reconciliation_required','ready_for_manual_review'])
                ->with('itinerary:id,name')->orderBy('updated_at')->paginate(30),
        ]);
    }
    public function review(Request $request,TripAssembly $assembly,TripAssemblyService $service): RedirectResponse
    {
        abort_unless(config('travel.trip_assembly_enabled',false),404);
        $item=$service->recheck($request->user(),$assembly);
        return back()->with('success','Trip snapshot checked: '.$item->status.'. No payment or supplier operation was attempted.');
    }
}
