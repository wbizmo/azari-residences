<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentInitiator
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly PaymentEligibilityService $eligibility,
        private readonly PaymentScheduleService $schedule,
    ) {}

    public function create(
        Booking $booking,
        string $provider,
        ?int $actorId = null,
        array $providerOptions = []
    ): Payment {
        $driver = $this->manager->driver($provider);
        if (! $driver->enabled()) {
            throw ValidationException::withMessages(['provider' => ucfirst($provider).' is not currently available.']);
        }
        $this->eligibility->assertCanInitiate($booking);

        [$payment, $new] = DB::transaction(function () use ($booking, $provider, $actorId): array {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail();
            $this->eligibility->assertCanInitiate($lockedBooking);
            $schedule = $this->schedule->amountToCollect($lockedBooking);
            $chargeAmount = (float) $schedule['amount'];

            if ($chargeAmount <= 0) {
                throw ValidationException::withMessages(['booking' => 'There is no payable balance for this booking.']);
            }

            $active = Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->where('payment_kind', '!=', 'amendment')
                ->where('initiated_at', '>=', now()->subMinutes(30))
                ->latest('initiated_at')
                ->first();

            if ($active) {
                $matchesCurrentSchedule = abs((float) $active->amount - $chargeAmount) < 0.01
                    && (string) ($active->payment_kind ?: 'full') === (string) $schedule['payment_kind'];

                if ($active->provider !== $provider) {
                    throw ValidationException::withMessages([
                        'provider' => 'A '.ucfirst($active->provider).' payment is already in progress for this booking. Continue that checkout or wait for it to expire.',
                    ]);
                }

                if ($matchesCurrentSchedule && filled($active->checkout_url)) {
                    return [$active, false];
                }

                if ($matchesCurrentSchedule && blank($active->checkout_url)) {
                    throw ValidationException::withMessages(['provider' => 'Payment initialization is already in progress. Please retry shortly.']);
                }

                $active->update([
                    'status' => 'abandoned',
                    'abandoned_at' => now(),
                ]);
            }

            Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->where('payment_kind', '!=', 'amendment')
                ->where('initiated_at', '<', now()->subMinutes(30))
                ->update(['status' => 'abandoned', 'abandoned_at' => now()]);

            do {
                $reference = 'PAY-'.now()->format('ymdHis').'-'.Str::upper(Str::random(8));
            } while (Payment::query()->where('reference', $reference)->exists());

            $payment = Payment::query()->create([
                'reference' => $reference,
                'provider' => $provider,
                'booking_id' => $lockedBooking->id,
                'user_id' => $lockedBooking->user_id,
                'guest_email' => $lockedBooking->guest_email,
                'amount' => $chargeAmount,
                'currency' => strtoupper($lockedBooking->currency),
                'status' => 'initiated',
                'payment_kind' => $schedule['payment_kind'],
                'due_on' => $schedule['due_on'],
                'initiated_at' => now(),
                'created_by' => $actorId,
                'creation_source' => $actorId ? 'administrator' : 'system',
            ]);

            return [$payment, true];
        }, 3);

        if (! $new) {
            return $payment;
        }

        try {
            $result = $driver->initialise([
                'reference' => $payment->reference,
                'booking_reference' => $booking->reference,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'name' => $booking->guest_name,
                'first_name' => $booking->guest_first_name,
                'last_name' => $booking->guest_last_name,
                'email' => $booking->guest_email,
                'phone' => $booking->guest_phone,
                'callback_url' => route('payments.callback', ['provider' => $provider, 'payment' => $payment->reference]),
                'webhook_url' => route('payments.webhook', ['provider' => $provider]),
                'instructions_url' => route('public.payment.flutterwave.instructions', [
                    'reference' => $booking->reference,
                    'payment' => $payment->reference,
                ]),
                'provider_options' => $providerOptions,
            ]);

            $checkoutUrl = trim((string) ($result['checkout_url'] ?? ''));
            if ($checkoutUrl === '' || ! filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
                throw new PaymentProviderException(
                    'The payment provider did not return a usable continuation URL.',
                    $provider
                );
            }

            // A fast provider webhook may finalize this payment while the
            // initialisation HTTP request is still returning. Never overwrite
            // the terminal status with "pending" (or a late "failed").
            $updated = Payment::query()->whereKey($payment->id)
                ->where('status', 'initiated')
                ->update([
                    'status' => 'pending',
                    'checkout_url' => $checkoutUrl,
                    'provider_reference' => filled($result['provider_reference'] ?? null)
                        ? $result['provider_reference']
                        : null,
                    'provider_response_summary' => $result['safe_response'] ?? null,
                    'updated_at' => now(),
                ]);
            if ($updated !== 1) {
                return $payment->refresh();
            }
            AuditLog::record(
                'payment.initialised',
                $payment,
                [],
                [
                    'provider' => $provider,
                    'booking_reference' => $booking->reference,
                    'payment_method' => $result['safe_response']['payment_method'] ?? null,
                ],
                actorId: $actorId
            );

            return $payment->refresh();
        } catch (\Throwable $e) {
            Payment::query()->whereKey($payment->id)
                ->where('status', 'initiated')
                ->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'provider_response_summary' => ['error' => 'Provider initialization failed.'],
                    'updated_at' => now(),
                ]);
            $payment->refresh();
            AuditLog::record(
                'payment.initialisation_failed',
                $payment,
                [],
                [],
                ['provider' => $provider, 'error_class' => $e::class],
                $actorId
            );
            throw $e;
        }
    }
}
