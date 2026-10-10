<?php

namespace App\Services\Travel;

use App\Contracts\Travel\TravelRefundVerifier;
use App\Models\TravelFulfillment;
use App\Models\TravelVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Independent travel refund ledger. It NEVER calls the accommodation refund
 * implementation or marks money returned until a payment provider attests it.
 */
final class TravelRefundReconciliationService
{
    public function recordVerifiedRefund(TravelFulfillment $fulfillment, string $providerRefundReference): TravelFulfillment
    {
        if (! preg_match('/^[A-Za-z0-9_.:-]{5,160}$/D', $providerRefundReference)) {
            throw ValidationException::withMessages(['refund' => 'Invalid provider refund reference.']);
        }
        if (! config('travel.fulfillment_enabled', false)) {
            throw ValidationException::withMessages(['refund' => 'Travel refunds are not enabled.']);
        }
        $class = config('travel.refund_verifier');
        if (! is_string($class) || ! class_exists($class)
            || ! is_subclass_of($class, TravelRefundVerifier::class)) {
            throw ValidationException::withMessages(['refund' => 'A certified travel refund verifier is required.']);
        }
        $proof = app($class)->verifyRefund($providerRefundReference);
        if (! is_array($proof) || ($proof['verified'] ?? false) !== true
            || ($proof['reference'] ?? null) !== $providerRefundReference
            || ! is_string($proof['original_capture'] ?? null)
            || ! is_int($proof['amount_minor'] ?? null)
            || $proof['amount_minor'] <= 0) {
            throw ValidationException::withMessages(['refund' => 'Refund was not independently verified.']);
        }

        return DB::transaction(function () use ($fulfillment, $proof, $providerRefundReference): TravelFulfillment {
            $locked = TravelFulfillment::query()->whereKey($fulfillment->id)
                ->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['support_required', 'cancelled', 'partially_refunded', 'refunded'], true)
                || $locked->payment_verified_at === null
                || $locked->verified_payment_reference !== $proof['original_capture']
                || $locked->currency !== ($proof['currency'] ?? null)) {
                throw ValidationException::withMessages(['refund' => 'Refund does not match an eligible paid travel order.']);
            }
            $eventKey = 'refund:'.hash('sha256', $providerRefundReference);
            $existing = DB::table('travel_financial_events')
                ->where('travel_fulfillment_id', $locked->id)
                ->where('event_key', $eventKey)->first();
            if ($existing) {
                if ($existing->type !== 'verified_refund'
                    || (int) $existing->amount_minor !== $proof['amount_minor']
                    || $existing->provider_reference !== $providerRefundReference) {
                    throw ValidationException::withMessages(['refund' => 'Existing refund evidence conflicts.']);
                }
                return $locked;
            }

            // The unique provider-reference claim table prevents applying one
            // actual refund to multiple travel orders across concurrent workers.
            $claim = DB::table('travel_refund_receipts')
                ->where('provider_reference', $providerRefundReference)
                ->lockForUpdate()->first();
            if ($claim) {
                throw ValidationException::withMessages(['refund' => 'Refund reference already belongs to another travel order.']);
            }

            $already = (int) DB::table('travel_financial_events')
                ->where('travel_fulfillment_id', $locked->id)
                ->where('type', 'verified_refund')->sum('amount_minor');
            if ($proof['amount_minor'] > (int) $locked->amount_minor - $already) {
                throw ValidationException::withMessages(['refund' => 'Verified refund exceeds the captured travel amount.']);
            }
            DB::table('travel_refund_receipts')->insert([
                'provider_reference' => $providerRefundReference,
                'travel_fulfillment_id' => $locked->id,
                'created_at' => now(),
            ]);
            DB::table('travel_financial_events')->insert([
                'travel_fulfillment_id' => $locked->id,
                'event_key' => $eventKey,
                'type' => 'verified_refund',
                'currency' => $locked->currency,
                'amount_minor' => $proof['amount_minor'],
                'provider_reference' => $providerRefundReference,
                'created_at' => now(),
            ]);
            $fullyRefunded = $already + $proof['amount_minor'] === (int) $locked->amount_minor;
            $locked->update(['status' => $fullyRefunded ? 'refunded' : 'partially_refunded']);
            $voucher = TravelVoucher::query()
                ->where('travel_fulfillment_id', $locked->id)->lockForUpdate()->first();
            if ($voucher && $voucher->status === 'issued') {
                $voucher->update(['status' => 'revoked']);
            }
            return $locked;
        }, 3);
    }
}
