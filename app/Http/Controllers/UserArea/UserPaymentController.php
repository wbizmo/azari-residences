<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentEligibilityService;
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
        abort_unless($payment->user_id === $request->user()->id || $payment->booking()->where('user_id', $request->user()->id)->exists(), 403);
        $payment->load('booking.property');
        $verificationAttempts = $payment->verificationAttempts()->paginate(10, ['*'], 'verifications')->withQueryString();
        $events = $payment->events()->paginate(10, ['*'], 'events')->withQueryString();
        return view('user.payments.show', compact('payment', 'verificationAttempts', 'events'));
    }

    public function retry(Request $request, Payment $payment, PaymentInitiator $initiator, PaymentEligibilityService $eligibility): RedirectResponse
    {
        abort_unless($payment->booking()->where('user_id', $request->user()->id)->exists(), 403);
        abort_unless($payment->canRetry(), 422);
        $eligibility->assertCanInitiate($payment->booking);
        $newPayment = $initiator->create($payment->booking, $payment->provider);
        return redirect()->away($newPayment->checkout_url);
    }
    public function resume(Request $request, Payment $payment): RedirectResponse
    {
        $payment = Payment::query()->with('booking')->whereKey($payment->id)->firstOrFail();
        abort_unless((int) $payment->booking?->user_id === (int) $request->user()->id, 403);
        abort_unless($payment->status === 'pending' && filled($payment->checkout_url), 422,
            'This payment is not awaiting checkout.');

        // Do not redirect to a stale gateway session or imply a paid booking
        // still requires payment. A new attempt must follow provider checks.
        abort_unless($payment->initiated_at && $payment->initiated_at->gt(now()->subMinutes(30))
            && ! $payment->booking->isPaid(), 422, 'This checkout link is no longer active.');

        $url = trim((string) $payment->checkout_url);
        abort_unless(filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && filled(parse_url($url, PHP_URL_HOST)), 422, 'The payment provider link is invalid.');

        return redirect()->away($url);
    }

}
