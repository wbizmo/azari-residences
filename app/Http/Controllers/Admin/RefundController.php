<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function store(
        Request $request,
        Payment $payment,
        RefundService $refunds
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $key = $data['idempotency_key']
            ?? hash('sha256', implode('|', [
                'admin-refund',
                $payment->getKey(),
                number_format((float) $data['amount'], 2, '.', ''),
                trim($data['reason']),
                $request->user()->getKey(),
            ]));

        $refund = $refunds->request(
            $payment,
            (float) $data['amount'],
            $request->user()->getKey(),
            $data['reason'],
            $key
        );

        return redirect()
            ->route('azari.admin.payments.show', $payment)
            ->with('success', 'Refund request '.$refund->reference.' recorded.');
    }

    public function update(
        Request $request,
        Payment $payment,
        Refund $refund,
        RefundService $refunds
    ): RedirectResponse {
        abort_unless((int) $refund->payment_id === (int) $payment->getKey(), 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['processing', 'successful', 'failed'])],
            'provider_reference' => ['nullable', 'string', 'max:190'],
            'safe_error' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'successful') {
            // A refund request (or a staff-entered arbitrary reference) is
            // not proof that a remote payment provider paid the customer.
            // Webhook/provider verification must settle non-manual refunds.
            if ($payment->provider !== 'manual') {
                throw ValidationException::withMessages([
                    'action' => 'This provider refund needs independently verified settlement evidence. Manual success marking is disabled.',
                ]);
            }

            abort_unless($request->user()->isAdministrator(), 403);

            if (blank($data['provider_reference'] ?? null)) {
                throw ValidationException::withMessages([
                    'provider_reference' => 'A verified manual refund transaction reference is required before confirming settlement.',
                ]);
            }
        }

        $actorId = $request->user()->getKey();

        $updated = match ($data['action']) {
            'processing' => $refunds->markProcessing(
                $refund,
                $data['provider_reference'] ?? null,
                $actorId
            ),
            'successful' => $refunds->markSuccessful(
                $refund,
                $data['provider_reference'] ?? null,
                $actorId,
                ['recorded_via' => 'admin_payment_screen']
            ),
            'failed' => $refunds->markFailed(
                $refund,
                (string) ($data['safe_error'] ?? 'Refund attempt failed.'),
                $actorId
            ),
        };

        return back()->with(
            $updated->status === 'successful' ? 'success' : 'warning',
            'Refund '.$updated->reference.' is now '.$updated->status.'.'
        );
    }
    public function dispatchProvider(
        Request $request,
        Payment $payment,
        Refund $refund,
        \App\Services\Payments\ProviderRefundExecutionService $executor
    ): RedirectResponse {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless((int) $refund->payment_id === (int) $payment->getKey(), 404);
        $updated = $executor->dispatch($refund, $request->user()->getKey());

        return back()->with('warning',
            'Provider accepted refund '.$updated->reference.'. Settlement is still pending independent verification.');
    }

    public function reconcileProvider(
        Request $request,
        Payment $payment,
        Refund $refund,
        \App\Services\Payments\ProviderRefundExecutionService $executor
    ): RedirectResponse {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless((int) $refund->payment_id === (int) $payment->getKey(), 404);
        $updated = $executor->reconcile($refund, $request->user()->getKey());

        return back()->with($updated->status === 'successful' ? 'success' : 'warning',
            'Provider reports refund status: '.$updated->status.'.');
    }

}
