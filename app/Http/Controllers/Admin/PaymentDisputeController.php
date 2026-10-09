<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentDispute;
use App\Services\Owners\OwnerDisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentDisputeController extends Controller
{
    public function store(Request $request, Payment $payment, OwnerDisputeService $service): RedirectResponse
    {
        $data = $request->validate([
            'provider_dispute_reference' => ['required', 'string', 'min:5', 'max:190'],
            'evidence_reference' => ['required', 'string', 'min:5', 'max:190'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);
        $service->open($payment, $request->user(), $data['provider_dispute_reference'],
            $data['evidence_reference'], (float) $data['amount']);

        return back()->with('success', 'Dispute recorded. Applicable owner funds have been reserved; a separate reviewer must determine the outcome.');
    }

    public function resolve(
        Request $request,
        PaymentDispute $dispute,
        OwnerDisputeService $service
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', 'in:won,lost'],
            'evidence_reference' => ['required', 'string', 'min:5', 'max:190'],
            'review_note' => ['required', 'string', 'min:8', 'max:2000'],
        ]);
        $service->resolve($dispute, $request->user(), $data['decision'],
            $data['evidence_reference'], $data['review_note']);

        return redirect()->route('azari.admin.payments.show', $dispute->payment_id)
            ->with('success', 'Dispute reviewed. The owner ledger has been reconciled or the reservation released.');
    }
}
