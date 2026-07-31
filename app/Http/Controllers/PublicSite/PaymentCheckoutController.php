<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\FlutterwaveService;
use App\Services\Payments\PaymentEligibilityService;
use App\Services\Payments\PaymentFinalizer;
use App\Services\Payments\PaymentInitiator;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentCheckoutController extends Controller
{
    public function select(
        Request $request,
        string $reference,
        PaymentManager $manager,
        PaymentEligibilityService $eligibility
    ): View {
        $booking = Booking::query()
            ->with(['property', 'payments'])
            ->where('reference', $reference)
            ->firstOrFail();
        $eligibility->assertCheckoutAccessible($booking);

        $providers = $manager->enabledProviders();
        $flutterwaveMethods = [];
        $flutterwaveBanks = [];

        $flutterwave = $providers['flutterwave'] ?? null;
        if ($flutterwave instanceof FlutterwaveService) {
            $flutterwaveMethods = $flutterwave->allowedPaymentMethods();

            if (strtoupper((string) $booking->currency) !== 'NGN') {
                $flutterwaveMethods = [];
                unset($providers['flutterwave']);
            } elseif (in_array('ussd', $flutterwaveMethods, true)) {
                try {
                    $flutterwaveBanks = $flutterwave->supportedBanks('NG');
                } catch (\Throwable $exception) {
                    report($exception);
                    $flutterwaveMethods = array_values(array_diff($flutterwaveMethods, ['ussd']));
                }
            }
        }

        return view('public.payments.select', [
            'booking' => $booking,
            'providers' => $providers,
            'fullAccess' => $this->hasFullAccess($request, $booking),
            'flutterwaveMethods' => $flutterwaveMethods,
            'flutterwaveBanks' => $flutterwaveBanks,
        ]);
    }

    public function initialise(
        Request $request,
        string $reference,
        PaymentInitiator $initiator,
        PaymentManager $manager,
        PaymentEligibilityService $eligibility
    ): RedirectResponse {
        $booking = Booking::query()->where('reference', $reference)->firstOrFail();

        if ($eligibility->isCancelled($booking)) {
            abort(404);
        }

        $eligibility->assertCanInitiate($booking);
        $providerNames = array_keys($manager->enabledProviders());
        $provider = $request->string('provider')->toString();

        $rules = ['provider' => ['required', Rule::in($providerNames)]];
        $providerOptions = [];

        if ($provider === 'flutterwave') {
            $driver = $manager->driver('flutterwave');
            abort_unless($driver instanceof FlutterwaveService, 422);

            $methods = $driver->allowedPaymentMethods();
            $rules['flutterwave_payment_method'] = ['required', Rule::in($methods)];
            $rules['flutterwave_ussd_bank'] = [
                Rule::requiredIf($request->string('flutterwave_payment_method')->toString() === 'ussd'),
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9_-]{2,20}$/',
            ];
        }

        $validated = $request->validate($rules);

        if ($provider === 'flutterwave') {
            $providerOptions = [
                'flutterwave_payment_method' => (string) $validated['flutterwave_payment_method'],
                'flutterwave_ussd_bank' => (string) ($validated['flutterwave_ussd_bank'] ?? ''),
            ];
        }

        try {
            $payment = $initiator->create(
                $booking,
                $provider,
                providerOptions: $providerOptions
            );

            return redirect()->away($payment->checkout_url);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'The payment provider could not start checkout. Please try again.');
        }
    }

    public function callback(
        Request $request,
        string $provider,
        string $payment,
        PaymentManager $manager,
        PaymentFinalizer $finalizer
    ): RedirectResponse {
        $record = Payment::query()
            ->where('reference', $payment)
            ->where('provider', $provider)
            ->firstOrFail();
        $driver = $manager->driver($provider);
        $providerReference = (string) (
            $request->input('charge_id')
            ?: $request->input('transaction_id')
            ?: $request->input('OrderTrackingId')
            ?: $request->input('orderTrackingId')
            ?: $request->input('id')
            ?: $record->provider_reference
        );
        $eventId = hash('sha256', implode('|', [
            $provider,
            $record->reference,
            $providerReference,
            json_encode($request->query()),
        ]));
        $event = PaymentEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            [
                'payment_id' => $record->id,
                'event_type' => 'payment.callback',
                'source' => 'callback',
                'signature_valid' => null,
                'processed' => false,
                'received_at' => now(),
                'safe_payload' => $request->only([
                    'status',
                    'reference',
                    'charge_id',
                    'transaction_id',
                    'OrderTrackingId',
                    'orderTrackingId',
                    'OrderMerchantReference',
                    'orderMerchantReference',
                    'id',
                ]),
            ]
        );

        try {
            $result = $driver->verify($providerReference);
            $finalizer->apply($record, $result, 'callback');
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => null]);
            $record->refresh();

            return redirect()
                ->route('public.payment.receipt', [$record->booking->reference, $record->reference])
                ->with(
                    $record->isSuccessful() ? 'success' : 'warning',
                    $record->isSuccessful()
                        ? 'Payment verified.'
                        : 'Payment is still being verified.'
                );
        } catch (\Throwable $e) {
            report($e);
            $event->update([
                'processed' => true,
                'processed_at' => now(),
                'safe_error' => 'Callback verification failed.',
            ]);

            return redirect()
                ->route('public.payment.select', $record->booking->reference)
                ->with('error', 'We could not verify the payment yet.');
        }
    }

    public function flutterwaveInstructions(
        Request $request,
        string $reference,
        string $payment
    ): View {
        $booking = Booking::query()
            ->with(['property', 'payments'])
            ->where('reference', $reference)
            ->firstOrFail();
        $record = $booking->payments()
            ->where('reference', $payment)
            ->where('provider', 'flutterwave')
            ->firstOrFail();

        abort_unless($this->hasFullAccess($request, $booking), 404);
        abort_if(blank($record->provider_reference), 404);

        return view('public.payments.flutterwave-instructions', [
            'booking' => $booking,
            'payment' => $record,
            'instruction' => (string) data_get(
                $record->provider_response_summary,
                'payment_instruction',
                'Follow the payment instruction supplied by Flutterwave, then verify the payment.'
            ),
        ]);
    }

    public function receipt(Request $request, string $reference, string $payment): Response
    {
        $booking = Booking::query()
            ->with(['property', 'payments'])
            ->where('reference', $reference)
            ->firstOrFail();
        $record = $booking->payments()->where('reference', $payment)->firstOrFail();

        return response()->view('public.payments.receipt', [
            'booking' => $booking,
            'payment' => $record,
            'fullAccess' => $this->hasFullAccess($request, $booking),
        ]);
    }

    public function webhook(
        Request $request,
        string $provider,
        PaymentWebhookProcessor $processor
    ): JsonResponse {
        abort_unless(in_array($provider, ['flutterwave', 'pesapal', 'intouch'], true), 404);

        if ($provider !== 'pesapal' && ! $request->isMethod('POST')) {
            abort(405);
        }

        $result = $processor->process($provider, $request);

        if ($provider === 'pesapal') {
            return response()->json([
                'orderNotificationType' => $result['event_type'] ?? 'IPNCHANGE',
                'orderTrackingId' => $result['provider_reference'] ?? (string) (
                    $request->input('OrderTrackingId')
                    ?: $request->input('orderTrackingId')
                ),
                'orderMerchantReference' => $result['merchant_reference'] ?? (string) (
                    $request->input('OrderMerchantReference')
                    ?: $request->input('orderMerchantReference')
                ),
                'status' => ($result['processed'] ?? false) ? 200 : 500,
            ], 200);
        }

        $status = ($result['invalid_signature'] ?? false)
            ? 401
            : (($result['malformed'] ?? false) ? 400 : 200);

        return response()->json(['received' => true, ...$result], $status);
    }

    private function hasFullAccess(Request $request, Booking $booking): bool
    {
        $user = $request->user();
        if ($user?->isStaff()) {
            return true;
        }
        if ($user && $booking->user_id === $user->id) {
            return true;
        }

        return (bool) $request->session()->get(
            'azari_guest_bookings.'.$booking->reference,
            false
        );
    }
}
