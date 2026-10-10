<?php

namespace App\Services\Travel;

use App\Models\TripAssembly;
use App\Models\TripItinerary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bounded recovery journal, NOT a fake cross-provider atomic checkout.
 * The actual supplier/payments retain independent idempotency and ownership.
 */
final class TripAssemblyService
{
    public function prepare(User $user, TripItinerary $trip, string $key): TripAssembly
    {
        abort_unless((int)$trip->user_id === (int)$user->id,404);
        return DB::transaction(function () use ($user,$trip,$key): TripAssembly {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing=TripAssembly::query()->where('user_id',$user->id)
                ->where('idempotency_key',$key)->first();
            if ($existing) {
                abort_unless((int)$existing->trip_itinerary_id === (int)$trip->id,409);
                return $existing;
            }
            $locked=TripItinerary::query()->whereKey($trip->id)->where('user_id',$user->id)
                ->lockForUpdate()->firstOrFail();
            $snapshot=app(TripAccountingService::class)->snapshot($locked);
            if (count($snapshot['items'])===0) {
                throw ValidationException::withMessages(['trip'=>'Add a stay or travel enquiry before preparing a trip.']);
            }
            $hash=hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));
            $assembly=TripAssembly::query()->create([
                'id'=>(string)Str::uuid(),'trip_itinerary_id'=>$locked->id,
                'user_id'=>$user->id,'idempotency_key'=>$key,'payload_hash'=>$hash,
                'status'=>'review_required','item_snapshot'=>$snapshot['items'],
                'currency_totals'=>$snapshot['totals'],
            ]);
            DB::table('trip_assembly_events')->insert([
                'trip_assembly_id'=>$assembly->id,'actor_id'=>$user->id,
                'action'=>'snapshot_created','previous_status'=>null,
                'new_status'=>'review_required',
                'details'=>json_encode(['fx_required'=>$snapshot['requires_fx_quote']],JSON_THROW_ON_ERROR),
                'created_at'=>now(),
            ]);
            return $assembly;
        },3);
    }

    public function recheck(User $staff, TripAssembly $assembly): TripAssembly
    {
        return DB::transaction(function () use ($staff,$assembly): TripAssembly {
            $item=TripAssembly::query()->whereKey($assembly->id)->lockForUpdate()->firstOrFail();
            $current=app(TripAccountingService::class)->snapshot($item->itinerary);
            $hash=hash('sha256',json_encode($current,JSON_THROW_ON_ERROR));
            $before=$item->status;
            // Never attempt external payment or change other component states.
            $status=$hash === $item->payload_hash ? 'ready_for_manual_review' : 'reconciliation_required';
            $item->update(['status'=>$status,'reviewed_at'=>now()]);
            DB::table('trip_assembly_events')->insert([
                'trip_assembly_id'=>$item->id,'actor_id'=>$staff->id,
                'action'=>'reconcile_snapshot','previous_status'=>$before,
                'new_status'=>$status,'details'=>json_encode([
                    'live_hash'=>$hash,'original_hash'=>$item->payload_hash
                ],JSON_THROW_ON_ERROR),'created_at'=>now()
            ]);
            return $item;
        },3);
    }
}
