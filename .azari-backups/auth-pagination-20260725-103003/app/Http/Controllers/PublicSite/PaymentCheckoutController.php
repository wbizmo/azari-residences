<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
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

class PaymentCheckoutController extends Controller
{
    public function select(Request $request, string $reference, PaymentManager $manager): View
    {
        $booking = Booking::query()->with(['property', 'payments'])->where('reference', $reference)->firstOrFail();
        $this->authorizeBooking($request, $booking);
        return view('public.payments.select', ['booking' => $booking, 'providers' => $manager->enabledProviders()]);
    }

    public function initialise(Request $request, string $reference, PaymentInitiator $initiator, PaymentManager $manager): RedirectResponse
    {
        $booking = Booking::query()->where('reference', $reference)->firstOrFail();
        $this->authorizeBooking($request, $booking);
        $request->validate(['provider' => ['required', Rule::in(array_keys($manager->enabledProviders()))]]);
        try {
            $payment = $initiator->create($booking, $request->string('provider')->toString());
            if (blank($payment->checkout_url)) {
                return back()->with('error', 'Payment checkout is temporarily unavailable. Your booking remains pending and no payment was recorded.');
            }
            return redirect()->away($payment->checkout_url);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'The payment provider could not start checkout. Please retry safely or choose another enabled provider.');
        }
    }

    public function callback(Request $request, string $provider, string $payment, PaymentManager $manager, PaymentFinalizer $finalizer): RedirectResponse
    {
        $record = Payment::query()->where('reference', $payment)->where('provider', $provider)->firstOrFail();
        $driver = $manager->driver($provider);
        $providerReference = (string) ($request->input('transaction_id')
            ?: $request->input('OrderTrackingId')
            ?: $request->input('orderTrackingId')
            ?: $request->input('reference')
            ?: $record->provider_reference);
        $eventId = hash('sha256', implode('|', [$provider, $record->reference, $providerReference, json_encode($request->query())]));
        $event = PaymentEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            [
                'payment_id' => $record->id,
                'event_type' => 'payment.callback',
                'source' => 'callback',
                'signature_valid' => null,
                'processed' => false,
                'received_at' => now(),
                'safe_payload' => Arr::only($request->all(), [
                    'status', 'tx_ref', 'transaction_id', 'OrderTrackingId', 'orderTrackingId',
                    'OrderMerchantReference', 'orderMerchantReference', 'reference',
                ]),
            ],
        );

        try {
            $result = $driver->verify($providerReference);
            $finalizer->apply($record, $result, 'callback');
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => null]);
            $record->refresh();
            return redirect()->route('azari.booking.summary', $record->booking->reference)
                ->with($record->isSuccessful() ? 'success' : 'warning', $record->isSuccessful() ? 'Payment verified and booking confirmed.' : 'Payment is still being verified.');
        } catch (\Throwable $e) {
            report($e);
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => 'Callback verification failed.']);
            return redirect()->route('public.payment.select', $record->booking->reference)
                ->with('error', 'We could not verify the payment yet. You may retry safely; duplicate payments are prevented.');
        }
    }

    public function webhook(Request $request, string $provider, PaymentWebhookProcessor $processor): JsonResponse
    {
        abort_unless(in_array($provider, ['flutterwave', 'pesapal', 'intouch'], true), 404);
        $result = $processor->process($provider, $request);
        $status = ($result['invalid_signature'] ?? false) ? 401 : (($result['malformed'] ?? false) ? 400 : 200);
        return response()->json(['received' => true, ...$result], $status);
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        if ($request->user()) {
            abort_unless($booking->user_id === $request->user()->id && ! $request->user()->isStaff(), 404);
            return;
        }
        abort_unless((bool) $request->session()->get('azari_guest_bookings.'.$booking->reference, false), 404);
    }
}
