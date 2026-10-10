<?php

namespace App\Services\Travel;

use App\Contracts\Travel\TravelPaymentVerifier;
use App\Models\TravelFulfillment;
use App\Models\TravelRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TravelFulfillmentService
{
    /**
     * This backend-only API is not exposed to guests or staff HTTP routes.
     * It can accept money evidence only from a licensed configured verifier.
     * Provider and payment servicing MUST be deployed together.
     */
    public function recordVerifiedCapture(TravelRequest $request, string $reference): TravelFulfillment
    {
        if (! config('travel.fulfillment_enabled', false)) {
            throw ValidationException::withMessages(['payment' => 'Travel settlement has not been enabled.']);
        }

        $class = config('travel.payment_verifier');
        if (! is_string($class) || ! class_exists($class)
            || ! is_subclass_of($class, TravelPaymentVerifier::class)) {
            throw ValidationException::withMessages(['payment' => 'No certified travel payment verifier is configured.']);
        }

        $proof = app($class)->verifyCapture($reference);
        if (! is_array($proof) || ($proof['verified'] ?? false) !== true
            || ($proof['reference'] ?? null) !== $reference
            || ! is_int($proof['amount_minor'] ?? null)
            || ! is_string($proof['currency'] ?? null)) {
            throw ValidationException::withMessages(['payment' => 'Payment was not independently verified.']);
        }

        return DB::transaction(function () use ($request, $proof, $reference): TravelFulfillment {
            $travel = TravelRequest::query()->with('supplier')->whereKey($request->id)
                ->lockForUpdate()->firstOrFail();

            if ($travel->status !== 'supplier_acknowledged'
                || $travel->expires_at->lte(now())
                || ! $travel->supplier->isApproved()
                || ! app(TravelPartnerGateway::class)->supports($travel->supplier)
                || ! $travel->data_share_consent) {
                throw ValidationException::withMessages(['payment' => 'Supplier enquiry is not eligible for fulfillment.']);
            }
            if ($proof['currency'] !== $travel->currency
                || $proof['amount_minor'] !== (int) $travel->quoted_total_minor) {
                throw ValidationException::withMessages(['payment' => 'Captured amount does not match the accepted quote.']);
            }

            $fulfillment = TravelFulfillment::query()->where('travel_request_id', $travel->id)
                ->lockForUpdate()->first();
            if ($fulfillment) {
                if ($fulfillment->verified_payment_reference !== $reference
                    || $fulfillment->currency !== $travel->currency
                    || (int) $fulfillment->amount_minor !== (int) $travel->quoted_total_minor) {
                    throw ValidationException::withMessages(['payment' => 'A different payment is already attached to this travel order.']);
                }
                return $fulfillment;
            }

            $fulfillment = TravelFulfillment::query()->create([
                'travel_request_id' => $travel->id,
                'travel_supplier_id' => $travel->travel_supplier_id,
                'status' => 'payment_verified',
                'verified_payment_reference' => $reference,
                'currency' => $travel->currency,
                'amount_minor' => $travel->quoted_total_minor,
                'payment_verified_at' => now(),
            ]);
            DB::table('travel_financial_events')->insert([
                'travel_fulfillment_id' => $fulfillment->id,
                'event_key' => 'capture:'.hash('sha256', $reference),
                'type' => 'verified_capture',
                'currency' => $travel->currency,
                'amount_minor' => $travel->quoted_total_minor,
                'provider_reference' => $reference,
                'created_at' => now(),
            ]);

            return $fulfillment;
        }, 3);
    }

    /**
     * Crash-safe dispatch: reserve state first and use a stable external
     * idempotency key. Ambiguous timeouts become reconciliation_required,
     * never "confirmed". Recovery must query the supplier by the same key.
     */
    public function requestSupplierReservation(TravelFulfillment $fulfillment): TravelFulfillment
    {
        if (! config('travel.fulfillment_enabled', false)) {
            throw ValidationException::withMessages(['fulfillment' => 'Provider reservations are disabled.']);
        }
        $locked = DB::transaction(function () use ($fulfillment): TravelFulfillment {
            $f = TravelFulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();
            $travel = $f->travelRequest;
            if ($travel->status !== 'supplier_acknowledged'
                || ! $travel->supplier->isApproved()
                || ! app(TravelPartnerGateway::class)->supports($travel->supplier)) {
                throw ValidationException::withMessages(['fulfillment' => 'Supplier integration is not approved.']);
            }
            if ($f->status === 'confirmed') {
                return $f;
            }
            if ($f->status !== 'payment_verified' || ! $f->payment_verified_at) {
                throw ValidationException::withMessages(['fulfillment' => 'Reservation is not ready. Reconcile any previous supplier attempt.']);
            }
            $f->update(['status' => 'dispatching']);
            return $f;
        }, 3);

        if ($locked->status === 'confirmed') {
            return $locked;
        }

        // The remote request is never made with a transaction/row lock held.
        $travel = $locked->travelRequest;
        try {
            $adapter = app(TravelPartnerGateway::class)->resolve($travel->supplier);
            $result = $adapter->reserve($travel, 'resavar-travel-'.$locked->id);
        } catch (Throwable) {
            // Ambiguous remote result: do not fabricate confirmation or retry.
            DB::transaction(function () use ($locked): void {
                TravelFulfillment::query()->whereKey($locked->id)
                    ->where('status', 'dispatching')
                    ->update(['status' => 'reconciliation_required']);
            }, 3);
            return $locked->fresh();
        }
        if (! is_array($result) || ($result['confirmed'] ?? false) !== true
            || ! is_string($result['reference'] ?? null)
            || ! preg_match('/^[A-Za-z0-9_.:-]{5,160}$/D', $result['reference'])
            || ($result['currency'] ?? null) !== $locked->currency
            || ($result['amount_minor'] ?? null) !== (int) $locked->amount_minor
            || ($travel->kind === 'flight' && (
                ! is_string($result['pnr'] ?? null)
                || ! preg_match('/^[A-Za-z0-9]{5,12}$/D', $result['pnr'])
                || ! is_array($result['ticket_numbers'] ?? null)
                || count($result['ticket_numbers']) !== (int) $travel->party_size
            ))) {
            TravelFulfillment::query()->whereKey($locked->id)
                ->where('status', 'dispatching')
                ->update(['status' => 'reconciliation_required']);
            return $locked->fresh();
        }

        $confirmed = DB::transaction(function () use ($locked, $result): TravelFulfillment {
            $f = TravelFulfillment::query()->whereKey($locked->id)->lockForUpdate()->firstOrFail();
            // A signed disruption/cancellation may arrive while the provider
            // request is in flight. Do not overwrite it with a confirmation.
            if ($f->travelRequest->status !== 'supplier_acknowledged') {
                $f->update(['status' => 'reconciliation_required']);
                return $f;
            }
            if ($f->status !== 'dispatching') {
                throw ValidationException::withMessages(['fulfillment' => 'Supplier result cannot overwrite the current state.']);
            }
            $f->update([
                'status' => 'confirmed',
                'provider_confirmation' => $result['reference'],
                'provider_confirmed_at' => now(),
                'confirmed_at' => now(),
            ]);
            return $f;
        }, 3);

        if ($confirmed->status !== 'confirmed') {
            return $confirmed;
        }

        if ($confirmed->travelRequest->kind === 'experience') {
            // Issue an admission voucher only after provider confirmation and
            // verified money are both durable. Retrying issuance is safe.
            app(TravelVoucherService::class)->issue($confirmed);
        }

        return $confirmed;
    }
}
