<?php

namespace App\Services\Travel;

use App\Contracts\Travel\DiningReservationVerifier;
use App\Models\DiningPartner;
use App\Models\DiningRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** No offline confirmations, staff-attested reservations or hotel mutation. */
final class DiningReservationVerificationService
{
    public function verify(User $staff,DiningRequest $dining,string $reference): DiningRequest
    {
        if (! config('travel.dining_provider_confirmation_enabled',false)) {
            throw ValidationException::withMessages(['provider'=>'Dining confirmations are not enabled.']);
        }
        $partner=$dining->partner;
        $key=(string)$partner->integration_key;
        $class=config('travel.dining_adapters.'.$key);
        if ($partner->status!=='published' || ! $partner->isDiscoverable()
            || ! $key || ! is_string($class) || ! class_exists($class)
            || ! is_subclass_of($class,DiningReservationVerifier::class)) {
            throw ValidationException::withMessages(['provider'=>'No authorized dining provider is configured.']);
        }

        $proof=app($class)->verify($dining,$reference);
        $states=['confirmed'=>'confirmed','unavailable'=>'unavailable','cancelled'=>'cancelled'];
        if (! is_array($proof) || ($proof['verified']??null)!==true
            || ($proof['reference']??null)!==$reference
            || ! isset($states[$proof['state']??''])
            || ($proof['dining_request_id']??null)!==$dining->id
            || (int)($proof['partner_id']??0)!==(int)$partner->id
            || (int)($proof['party_size']??0)!==(int)$dining->party_size
            || ! is_string($proof['requested_for']??null)
            || ! \Illuminate\Support\Carbon::parse($proof['requested_for'])
                ->equalTo($dining->requested_for)) {
            throw ValidationException::withMessages(['provider'=>'Provider reservation evidence does not match this request.']);
        }

        return DB::transaction(function () use ($dining,$staff,$partner,$proof,$states,$reference): DiningRequest {
            // Both partner and request are locked; the verified evidence is
            // rechecked under transaction so no late cancellation can flip it.
            $active=DiningPartner::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
            $item=DiningRequest::query()->whereKey($dining->id)->lockForUpdate()->firstOrFail();
            if (! $active->isDiscoverable()) {
                throw ValidationException::withMessages(['provider'=>'Dining provider has been suspended.']);
            }
            $next=$states[$proof['state']];
            if ($item->status===$next &&
                ($item->provider_reference===$reference ||
                DB::table('dining_request_events')->where('dining_request_id',$item->id)
                    ->where('provider_reference',$reference)->where('new_status',$next)->exists())) {
                return $item;
            }

            if (($next==='confirmed' && ($item->status!=='pending_concierge' || ! $item->supplier_share_consent))
                || ($next==='unavailable' && $item->status!=='pending_concierge')
                || ($next==='cancelled' && ! in_array($item->status,['cancellation_requested','confirmed'],true))) {
                throw ValidationException::withMessages(['provider'=>'Provider response is stale or conflicts with cancellation.']);
            }
            $before=$item->status;
            $item->update([
                'status'=>$next,'provider_reference'=>$next==='cancelled' ? $item->provider_reference : $reference,
                'provider_confirmed_at'=>$next==='confirmed'?now():$item->provider_confirmed_at,
                'cancelled_at'=>$next==='cancelled'?now():$item->cancelled_at,
            ]);
            DB::table('dining_request_events')->insert([
                'dining_request_id'=>$item->id,'actor_id'=>$staff->id,
                'previous_status'=>$before,'new_status'=>$next,
                'provider_reference'=>$reference,'created_at'=>now(),
            ]);
            return $item;
        },3);
    }
}
