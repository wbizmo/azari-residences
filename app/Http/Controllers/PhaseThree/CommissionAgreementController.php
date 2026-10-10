<?php

namespace App\Http\Controllers\PhaseThree;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\PhaseThree\CommissionAgreementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommissionAgreementController extends Controller
{
    public function __invoke(Request $request, Property $property, CommissionAgreementService $agreements): RedirectResponse
    {
        $data = $request->validate([
            'basis_points' => ['required','integer','min:0','max:3000'],
            'effective_from' => ['required','date','after:today'],
        ]);
        $agreements->approve($property, $request->user(), (int) $data['basis_points'],
            CarbonImmutable::parse($data['effective_from'])->startOfDay());
        return back()->with('success', 'Future commission agreement version approved. Historic earnings were not changed.');
    }
}
