<?php

namespace App\Services\Travel;

use App\Models\Booking;
use App\Models\DiningPartner;
use App\Models\DiningRequest;
use App\Models\TripItinerary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DiningConciergeService
{
    public function create(User $user, DiningPartner $partner, array $input): DiningRequest
    {
        return DB::transaction(function () use ($user, $partner, $input): DiningRequest {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $key = (string) $input['idempotency_key'];
            $payload = [
                'partner' => $partner->id,
                'party' => (int) $input['party_size'],
                'requested_for' => (string) $input['requested_for'],
                'trip' => (int) ($input['trip_itinerary_id'] ?? 0),
                'booking' => (int) ($input['booking_id'] ?? 0),
                'dietary_notes' => trim((string) ($input['dietary_notes'] ?? '')),
                'share_consent' => (bool) ($input['supplier_share_consent'] ?? false),
            ];
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = DiningRequest::query()->where('user_id',$user->id)
                ->where('idempotency_key',$key)->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key'=>'This key belongs to different request details.']);
                }
                return $existing;
            }
            $active = DiningPartner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            if (! $active->isDiscoverable()) {
                throw ValidationException::withMessages(['dining_partner_id'=>'This dining location is not currently verified.']);
            }
            if (! \Illuminate\Support\Carbon::parse($input['requested_for'])->isFuture()) {
                throw ValidationException::withMessages(['requested_for'=>'Choose a future time.']);
            }
            if ($payload['trip'] && ! TripItinerary::query()
                ->whereKey($payload['trip'])->where('user_id',$user->id)->exists()) {
                throw ValidationException::withMessages(['trip_itinerary_id'=>'This itinerary is unavailable.']);
            }
            if ($payload['booking']) {
                $booking = Booking::query()->whereKey($payload['booking'])
                    ->where('user_id',$user->id)->first();
                if (! $booking || ($payload['trip'] && (int) $booking->trip_itinerary_id !== $payload['trip'])) {
                    throw ValidationException::withMessages(['booking_id'=>'The selected stay is not in your itinerary.']);
                }
            }
            return DiningRequest::query()->create([
                'id'=>(string) Str::uuid(),'user_id'=>$user->id,
                'dining_partner_id'=>$active->id,
                'trip_itinerary_id'=>$payload['trip'] ?: null,
                'booking_id'=>$payload['booking'] ?: null,
                'party_size'=>$payload['party'],'requested_for'=>$input['requested_for'],
                'idempotency_key'=>$key,'payload_hash'=>$hash,
                'private_preferences'=>['dietary_notes'=>$payload['dietary_notes']],
                'supplier_share_consent'=>$payload['share_consent'],
                'status'=>'pending_concierge',
            ]);
        },3);
    }

    public function cancel(User $user, DiningRequest $dining): DiningRequest
    {
        abort_unless((int)$dining->user_id === (int)$user->id, 404);
        return DB::transaction(function () use ($user,$dining): DiningRequest {
            $item = DiningRequest::query()->whereKey($dining->id)
                ->where('user_id',$user->id)->lockForUpdate()->firstOrFail();
            if (in_array($item->status,['cancelled','cancellation_requested'],true)) return $item;
            $next = $item->status === 'pending_concierge' ? 'cancelled' : 'cancellation_requested';
            $item->update(['status'=>$next,'cancelled_at'=>$next==='cancelled'?now():null]);
            return $item;
        },3);
    }
}
