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
    ) {}

    public function create(Booking $booking, string $provider, ?int $actorId = null): Payment
    {
        $driver = $this->manager->driver($provider);
        if (! $driver->enabled()) {
            throw ValidationException::withMessages(['provider' => ucfirst($provider).' is not currently available.']);
        }
        $this->eligibility->assertCanInitiate($booking);

        [$payment, $new] = DB::transaction(function () use ($booking, $provider, $actorId): array {
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->assertCanInitiate($lockedBooking);
            $balance = $lockedBooking->balanceDue();
            if ($balance <= 0) {
                throw ValidationException::withMessages(['booking' => 'This booking is already fully paid.']);
            }

            $active = Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->where('initiated_at', '>=', now()->subMinutes(30))
                ->latest('initiated_at')
                ->first();

            if ($active) {
                if ($active->provider !== $provider) {
                    throw ValidationException::withMessages([
                        'provider' => 'A '.ucfirst($active->provider).' payment is already in progress for this booking. Continue that checkout or wait for it to expire.',
                    ]);
                }
                if (blank($active->checkout_url)) {
                    throw ValidationException::withMessages(['provider' => 'Payment initialization is already in progress. Please retry shortly.']);
                }
                return [$active, false];
            }

            Payment::query()
                ->where('booking_id', $lockedBooking->id)
                ->whereIn('status', ['initiated', 'pending'])
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
                'amount' => $balance,
                'currency' => strtoupper($lockedBooking->currency),
                'status' => 'initiated',
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
            ]);

            $payment->update([
                'status' => 'pending',
                'checkout_url' => $result['checkout_url'] ?? null,
                'provider_reference' => filled($result['provider_reference'] ?? null) ? $result['provider_reference'] : null,
                'provider_response_summary' => $result['safe_response'] ?? null,
            ]);
            AuditLog::record('payment.initialised', $payment, [], ['provider' => $provider, 'booking_reference' => $booking->reference], actorId: $actorId);

            return $payment->refresh();
        } catch (\Throwable $e) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'provider_response_summary' => ['error' => 'Provider initialization failed.'],
            ]);
            AuditLog::record('payment.initialisation_failed', $payment, [], [], ['provider' => $provider, 'error_class' => $e::class], $actorId);
            throw $e;
        }
    }
}
