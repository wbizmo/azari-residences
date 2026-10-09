<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingModificationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentProviderException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Standalone add-on payment for an expiring date amendment. It never
 * changes the existing booking price or dates before guest acceptance.
 */
class BookingAmendmentPaymentService
{
    public function __construct(private readonly PaymentManager $providers) {}

    public function initiate(
        Booking $booking,
        BookingModificationRequest $change,
        User $guest,
        string $provider = 'flutterwave'
    ): Payment {
        abort_unless((int) $booking->user_id === (int) $guest->getKey(), 403);
        if ($provider !== 'flutterwave' || ! $this->providers->driver($provider)->enabled()) {
            throw ValidationException::withMessages(['provider' => 'This amendment payment method is unavailable.']);
        }

        [$payment, $new] = DB::transaction(function () use ($booking, $change, $guest, $provider): array {
            $lock = fn (Builder $q) => DB::connection()->getDriverName() === 'sqlite'
                ? $q : $q->lockForUpdate();
            $bookingRow = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite', $lock)->firstOrFail();
            $changeRow = BookingModificationRequest::query()
                ->whereKey($change->getKey())
                ->where('booking_id', $bookingRow->getKey())
                ->where('user_id', $guest->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite', $lock)->firstOrFail();

            if ($changeRow->status !== 'quoted' || ! $changeRow->quote_expires_at?->isFuture()
                || ! in_array($bookingRow->status, ['paid', 'confirmed'], true)
                || ! $bookingRow->check_in?->isFuture() || $bookingRow->isCancelled()) {
                throw ValidationException::withMessages([
                    'change' => 'Date-change offer has expired or is no longer eligible for payment.',
                ]);
            }
            $offer = $changeRow->price_quote ?? [];
            $delta = round((float) ($offer['delta'] ?? 0), 2);
            if ($delta <= 0 || ! isset($offer['old_total'], $offer['new_total'])
                || round((float) $bookingRow->total, 2) !== round((float) $offer['old_total'], 2)
                || strtoupper((string) $bookingRow->currency) !== strtoupper((string) ($offer['currency'] ?? ''))
                || $bookingRow->updated_at?->toIso8601String() !== ($offer['booking_updated_at'] ?? null)) {
                throw ValidationException::withMessages([
                    'change' => 'The old booking or quoted payable difference has changed. Request a new quote.',
                ]);
            }
            if ($changeRow->payment_id) {
                $previous = Payment::query()->whereKey($changeRow->payment_id)->firstOrFail();
                if ((int) $previous->booking_id !== (int) $bookingRow->getKey()
                    || (float) $previous->amount !== $delta || $previous->provider !== $provider) {
                    throw ValidationException::withMessages(['payment' => 'Amendment payment reference is inconsistent.']);
                }
                if (in_array($previous->status, ['initiated', 'pending'], true) && filled($previous->checkout_url)) {
                    return [$previous, false];
                }
                throw ValidationException::withMessages([
                    'payment' => 'An amendment payment is already initiated or awaits reconciliation. Do not pay again.',
                ]);
            }

            $payment = Payment::query()->create([
                'reference' => 'PAY-AMD-'.now()->format('ymdHis').'-'.Str::upper(Str::random(8)),
                'booking_id' => $bookingRow->getKey(), 'user_id' => $guest->getKey(),
                'provider' => $provider, 'guest_email' => $bookingRow->guest_email,
                'amount' => $delta, 'currency' => strtoupper((string) $bookingRow->currency),
                'status' => 'initiated', 'payment_kind' => 'amendment',
                'creation_source' => 'amendment', 'initiated_at' => now(),
            ]);
            $changeRow->forceFill(['payment_id' => $payment->getKey()])->save();

            return [$payment, true];
        }, 5);

        if (! $new) {
            return $payment;
        }

        // Do not automatically retry ambiguous provider initiations; another
        // call could create an additional charge outside this database.
        try {
            $driver = $this->providers->driver($provider);
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
                    'reference' => $booking->reference, 'payment' => $payment->reference,
                ]),
                'provider_options' => [],
            ]);
            $url = trim((string) ($result['checkout_url'] ?? ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \UnexpectedValueException('Provider returned an invalid checkout address.');
            }
            // Grant a bounded checkout window while still requiring a fresh
            // inventory and price check at eventual guest acceptance.
            BookingModificationRequest::query()->where('payment_id', $payment->getKey())
                ->where('status', 'quoted')->update(['quote_expires_at' => now()->addMinutes(30)]);
            $payment->forceFill([
                'checkout_url' => $url, 'status' => 'pending',
                'provider_reference' => $result['provider_reference'] ?? null,
                'provider_response_summary' => $result['safe_response'] ?? null,
            ])->save();

            return $payment->refresh();
        } catch (\Throwable $exception) {
            Payment::query()->whereKey($payment->getKey())->where('status', 'initiated')
                ->update([
                    'status' => 'reconciliation_required',
                    'administrative_note' => 'Amendment checkout creation outcome unknown. Verify provider before retry.',
                ]);
            report($exception);
            throw new PaymentProviderException(
                'Payment checkout could not be confirmed. Contact support before retrying to avoid a duplicate charge.',
                $provider
            );
        }
    }
}
