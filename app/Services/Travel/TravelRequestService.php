<?php

namespace App\Services\Travel;

use App\Models\Booking;
use App\Models\TravelExperienceSlot;
use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TravelRequestEvent;
use App\Models\TripItinerary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TravelRequestService
{
    /**
     * An enquiry / short-lived capacity hold, NEVER a paid reservation or ticket.
     * Lock order for all experience changes: slot -> request. The user row
     * serializes repeated idempotency keys without depending on advisory locks.
     */
    public function create(User $user, TravelOffer $offer, array $input): TravelRequest
    {
        return DB::transaction(function () use ($user, $offer, $input): TravelRequest {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $payload = [
                'offer_id' => (int) $offer->id,
                'party_size' => (int) $input['party_size'],
                'slot_id' => isset($input['slot_id']) ? (int) $input['slot_id'] : null,
                'trip_itinerary_id' => isset($input['trip_itinerary_id']) ? (int) $input['trip_itinerary_id'] : null,
                'booking_id' => isset($input['booking_id']) ? (int) $input['booking_id'] : null,
                'preferences' => [
                    'bags' => (int) ($input['bags'] ?? 0),
                    'accessible' => (bool) ($input['accessible'] ?? false),
                    'flight_number' => $input['flight_number'] ?? null,
                ],
                'data_share_consent' => (bool) ($input['data_share_consent'] ?? false),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $key = (string) $input['idempotency_key'];

            $existing = TravelRequest::query()->where('user_id', $user->id)
                ->where('idempotency_key', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This request key was used for different details.',
                    ]);
                }

                return $existing;
            }

            $lockedOffer = TravelOffer::query()->with('supplier')
                ->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if (! $lockedOffer->isRequestable()) {
                throw ValidationException::withMessages([
                    'offer_id' => 'This supplier offer is not currently accepting requests.',
                ]);
            }

            if ($payload['party_size'] > $lockedOffer->max_party) {
                throw ValidationException::withMessages(['party_size' => 'The requested party exceeds the published limit.']);
            }

            if ($payload['trip_itinerary_id'] && ! TripItinerary::query()
                ->whereKey($payload['trip_itinerary_id'])->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['trip_itinerary_id' => 'Invalid travel itinerary.']);
            }

            if ($payload['booking_id']) {
                $booking = Booking::query()->whereKey($payload['booking_id'])
                    ->where('user_id', $user->id)->first();
                if (! $booking) {
                    throw ValidationException::withMessages(['booking_id' => 'Invalid associated stay.']);
                }
                if ($payload['trip_itinerary_id'] && $booking->trip_itinerary_id
                    && (int) $booking->trip_itinerary_id !== $payload['trip_itinerary_id']) {
                    throw ValidationException::withMessages(['booking_id' => 'This stay belongs to another itinerary.']);
                }
            }

            $slot = null;
            if ($lockedOffer->kind === 'experience') {
                if (! $payload['slot_id']) {
                    throw ValidationException::withMessages(['slot_id' => 'Choose an available activity time.']);
                }
                $slot = TravelExperienceSlot::query()
                    ->whereKey($payload['slot_id'])
                    ->where('travel_offer_id', $lockedOffer->id)
                    ->lockForUpdate()->first();
                if (! $slot || $slot->starts_at->lte(now())) {
                    throw ValidationException::withMessages(['slot_id' => 'This activity time is unavailable.']);
                }

                // Capacity is recomputed from unexpired request rows under a
                // slot row lock. No mutable cached counter can drift on crash.
                TravelRequest::query()->where('travel_experience_slot_id', $slot->id)
                    ->whereIn('status', ['requested', 'supplier_acknowledged'])
                    ->where('expires_at', '<=', now())->update(['status' => 'expired']);
                $held = (int) TravelRequest::query()
                    ->where('travel_experience_slot_id', $slot->id)
                    ->whereIn('status', ['requested', 'supplier_acknowledged'])
                    ->where('expires_at', '>', now())->sum('party_size');
                if ($held + $payload['party_size'] > $slot->capacity) {
                    throw ValidationException::withMessages(['slot_id' => 'Not enough spaces remain for this time.']);
                }
            } elseif ($payload['slot_id']) {
                throw ValidationException::withMessages(['slot_id' => 'Slots are only valid for activities.']);
            }

            $expires = now()->addMinutes((int) config('travel.request_ttl_minutes', 15));
            if ($lockedOffer->expires_at->lt($expires)) {
                $expires = $lockedOffer->expires_at;
            }

            $request = TravelRequest::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'trip_itinerary_id' => $payload['trip_itinerary_id'],
                'booking_id' => $payload['booking_id'],
                'travel_supplier_id' => $lockedOffer->travel_supplier_id,
                'travel_offer_id' => $lockedOffer->id,
                'travel_experience_slot_id' => $slot?->id,
                'kind' => $lockedOffer->kind,
                'status' => 'requested',
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'party_size' => $payload['party_size'],
                'quoted_total_minor' => $lockedOffer->totalMinor($payload['party_size']),
                'currency' => $lockedOffer->currency,
                'quote_snapshot' => [
                    'title' => $lockedOffer->title,
                    'origin' => $lockedOffer->origin,
                    'destination' => $lockedOffer->destination,
                    'timezone' => $lockedOffer->timezone,
                    'starts_at' => $slot?->starts_at?->toIso8601String()
                        ?? $lockedOffer->starts_at?->toIso8601String(),
                    'unit_base_minor' => $lockedOffer->base_minor,
                    'unit_tax_minor' => $lockedOffer->tax_minor,
                    'unit_fee_minor' => $lockedOffer->fee_minor,
                    'refundable_deposit_minor' => $lockedOffer->deposit_minor,
                    'terms' => $lockedOffer->terms,
                    'eligibility' => $lockedOffer->eligibility,
                    'indicative_only' => true,
                    'requires_supplier_confirmation_and_separate_payment' => true,
                ],
                'preferences' => $payload['preferences'],
                'data_share_consent' => $payload['data_share_consent'],
                'expires_at' => $expires,
            ]);

            $this->event($request, 'request_created', null, 'requested', $user->id);

            return $request;
        }, 3);
    }

    public function cancel(User $user, TravelRequest $request): TravelRequest
    {
        abort_unless((int) $request->user_id === (int) $user->id, 404);

        return DB::transaction(function () use ($user, $request): TravelRequest {
            if ($request->travel_experience_slot_id) {
                TravelExperienceSlot::query()->whereKey($request->travel_experience_slot_id)
                    ->lockForUpdate()->firstOrFail();
            }
            $locked = TravelRequest::query()->whereKey($request->id)
                ->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            if (! in_array($locked->status, ['requested', 'supplier_acknowledged', 'expired'], true)) {
                throw ValidationException::withMessages([
                    'request' => 'This travel request must be serviced by support.',
                ]);
            }

            $before = $locked->status;
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->event($locked, 'guest_cancelled', $before, 'cancelled', $user->id);

            // Deliberately no Booking/Payment/Refund mutations.
            return $locked;
        }, 3);
    }

    public function review(TravelRequest $request, User $staff, bool $accepted, ?string $supplierReference): TravelRequest
    {
        return DB::transaction(function () use ($request, $staff, $accepted, $supplierReference): TravelRequest {
            if ($request->travel_experience_slot_id) {
                TravelExperienceSlot::query()->whereKey($request->travel_experience_slot_id)
                    ->lockForUpdate()->firstOrFail();
            }
            $locked = TravelRequest::query()->with('supplier')
                ->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'requested' || $locked->expires_at->lte(now())) {
                throw ValidationException::withMessages(['request' => 'Only current, pending requests can be reviewed.']);
            }
            if ($accepted && (! $locked->supplier->isApproved()
                || ! $locked->data_share_consent || ! filled($supplierReference))) {
                throw ValidationException::withMessages([
                    'supplier_reference' => 'Supplier approval, consent and a verified acknowledgment reference are required.',
                ]);
            }

            // Still not a booking/ticket. Verified payment and an authorized
            // provider contract are separate release gates.
            $next = $accepted ? 'supplier_acknowledged' : 'supplier_declined';
            $locked->update([
                'status' => $next,
                'supplier_reference' => $accepted ? $supplierReference : null,
                'supplier_acknowledged_at' => $accepted ? now() : null,
            ]);
            $this->event($locked, 'supplier_review', 'requested', $next, $staff->id);

            return $locked;
        }, 3);
    }

    private function event(TravelRequest $request, string $kind, ?string $before, string $after, ?int $actorId): void
    {
        TravelRequestEvent::query()->create([
            'travel_request_id' => $request->id,
            'actor_id' => $actorId,
            'event_type' => $kind,
            'previous_status' => $before,
            'new_status' => $after,
            'created_at' => now(),
        ]);
    }
}
