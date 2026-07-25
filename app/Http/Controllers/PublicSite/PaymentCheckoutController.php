<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\PaymentEligibilityService;
use App\Services\Payments\PaymentFinalizer;
use App\Services\Payments\PaymentInitiator;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentCheckoutController extends Controller
{
    public function select(Request $request, string $reference, PaymentManager $manager, PaymentEligibilityService $eligibility): View
    {
        $booking = Booking::query()->with(['property', 'payments'])->where('reference', $reference)->firstOrFail();
        $eligibility->assertCheckoutAccessible($booking);

        return view('public.payments.select', [
            'booking' => $booking,
            'providers' => $manager->enabledProviders(),
            'fullAccess' => $this->hasFullAccess($request, $booking),
        ]);
    }

    public function initialise(Request $request, string $reference, PaymentInitiator $initiator, PaymentManager $manager, PaymentEligibilityService $eligibility): RedirectResponse
    {
        $booking = Booking::query()->where('reference', $reference)->firstOrFail();

        if ($eligibility->isCancelled($booking)) {
            abort(404);
        }

        $eligibility->assertCanInitiate($booking);
        $request->validate(['provider' => ['required', Rule::in(array_keys($manager->enabledProviders()))]]);

        try {
            $payment = $initiator->create($booking, $request->string('provider')->toString());
            if (blank($payment->checkout_url)) {
                return back()->with('error', 'Payment checkout is temporarily unavailable. No payment was recorded.');
            }
            return redirect()->away($payment->checkout_url);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'The payment provider could not start checkout. Please try again.');
        }
    }

    public function callback(Request $request, string $provider, string $payment, PaymentManager $manager, PaymentFinalizer $finalizer): RedirectResponse
    {
        $record = Payment::query()->where('reference', $payment)->where('provider', $provider)->firstOrFail();
        $driver = $manager->driver($provider);
        $providerReference = (string) ($request->input('transaction_id') ?: $request->input('OrderTrackingId') ?: $request->input('orderTrackingId') ?: $request->input('reference') ?: $record->provider_reference);
        $eventId = hash('sha256', implode('|', [$provider, $record->reference, $providerReference, json_encode($request->query())]));
        $event = PaymentEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            ['payment_id' => $record->id, 'event_type' => 'payment.callback', 'source' => 'callback', 'signature_valid' => null, 'processed' => false, 'received_at' => now(), 'safe_payload' => Arr::only($request->all(), ['status', 'tx_ref', 'transaction_id', 'OrderTrackingId', 'orderTrackingId', 'OrderMerchantReference', 'orderMerchantReference', 'reference'])]
        );

        try {
            $result = $driver->verify($providerReference);
            $finalizer->apply($record, $result, 'callback');
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => null]);
            $record->refresh();
            return redirect()->route('public.payment.receipt', [$record->booking->reference, $record->reference])
                ->with($record->isSuccessful() ? 'success' : 'warning', $record->isSuccessful() ? 'Payment verified.' : 'Payment is still being verified.');
        } catch (\Throwable $e) {
            report($e);
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => 'Callback verification failed.']);
            return redirect()->route('public.payment.select', $record->booking->reference)->with('error', 'We could not verify the payment yet.');
        }
    }

    public function receipt(Request $request, string $reference, string $payment): Response
    {
        $booking = Booking::query()->with(['property', 'payments'])->where('reference', $reference)->firstOrFail();
        $record = $booking->payments()->where('reference', $payment)->firstOrFail();

        return response()->view('public.payments.receipt', [
            'booking' => $booking,
            'payment' => $record,
            'fullAccess' => $this->hasFullAccess($request, $booking),
        ]);
    }

    public function webhook(Request $request, string $provider, PaymentWebhookProcessor $processor): JsonResponse
    {
        abort_unless(in_array($provider, ['flutterwave', 'pesapal', 'intouch'], true), 404);
        $result = $processor->process($provider, $request);
        $status = ($result['invalid_signature'] ?? false) ? 401 : (($result['malformed'] ?? false) ? 400 : 200);
        return response()->json(['received' => true, ...$result], $status);
    }

    private function hasFullAccess(Request $request, Booking $booking): bool
    {
        $user = $request->user();
        if ($user?->isStaff()) return true;
        if ($user && $booking->user_id === $user->id) return true;
        return (bool) $request->session()->get('azari_guest_bookings.'.$booking->reference, false);
    }
}
