<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentInitiator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $query = $request->user()->payments()->with('booking.property');
        if ($status !== 'all') $query->where('status', $status);
        return view('user.payments.index', ['payments' => $query->latest()->paginate(10)->withQueryString(), 'status' => $status]);
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless($payment->user_id === $request->user()->id || $payment->booking()->where('user_id', $request->user()->id)->exists(), 404);
        $payment->load('booking.property');
        $verificationAttempts = $payment->verificationAttempts()->paginate(10, ['*'], 'verifications')->withQueryString();
        $events = $payment->events()->paginate(10, ['*'], 'events')->withQueryString();
        return view('user.payments.show', compact('payment', 'verificationAttempts', 'events'));
    }

    public function retry(Request $request, Payment $payment, PaymentInitiator $initiator): RedirectResponse
    {
        abort_unless($payment->booking()->where('user_id', $request->user()->id)->exists(), 404);
        abort_unless($payment->canRetry(), 422);
        $newPayment = $initiator->create($payment->booking, $payment->provider);
        return redirect()->away($newPayment->checkout_url);
    }
}
