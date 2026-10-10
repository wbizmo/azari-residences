<?php

namespace App\Services\Travel;

use App\Contracts\Travel\TripProductRecoveryAdapter;
use App\Models\TripAssembly;
use App\Models\TripAssemblyStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Explicit saga checkpoints. No distributed transaction or payment mutation.
 * An uncertain provider outcome remains uncertain until independently
 * reconciled against an approved provider.
 */
final class TripRecoveryService
{
    public function prepare(TripAssembly $assembly): int
    {
        return DB::transaction(function () use ($assembly): int {
            $locked=TripAssembly::query()->whereKey($assembly->id)->lockForUpdate()->firstOrFail();
            $added=0;
            foreach($locked->item_snapshot as $item) {
                $type=(string)$item['type'];
                $id=(string)$item['id'];
                $status=$type==='stay' ? 'preexisting_stay' : 'awaiting_partner';
                $created=DB::table('trip_assembly_steps')->insertOrIgnore([
                    'trip_assembly_id'=>$locked->id,'item_type'=>$type,'item_id'=>$id,
                    'status'=>$status,
                    'operation_key'=>hash('sha256',$locked->id.'|'.$type.'|'.$id),
                    'attempts'=>0,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $added+=$created;
            }
            return $added;
        },3);
    }

    public function reconcile(User $staff, TripAssemblyStep $step): TripAssemblyStep
    {
        $this->prepare($step->assembly);
        $claimed=DB::transaction(function () use ($step): TripAssemblyStep {
            $current=TripAssemblyStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($current->status==='preexisting_stay') {
                throw ValidationException::withMessages(['step'=>'Accommodation remains independently managed.']);
            }
            if (! in_array($current->status,
                ['awaiting_partner','needs_provider','reconciliation_required','compensation_requested'],true)) {
                throw ValidationException::withMessages(['step'=>'This component cannot be retried in its present state.']);
            }
            $current->update(['status'=>'checking','attempts'=>$current->attempts+1,
                'last_checked_at'=>now()]);
            return $current;
        },3);

        $adapter=$this->adapter($claimed->item_type);
        if (! $adapter) {
            $this->settle($claimed, 'needs_provider', null);
            return $claimed->fresh();
        }
        try {
            $evidence=$adapter->reconcile($claimed->item_id,$claimed->operation_key);
        } catch (Throwable) {
            $this->settle($claimed, 'reconciliation_required', null);
            return $claimed->fresh();
        }
        // Never mark confirmed on a bare true/HTTP 200. Proof must bind to the
        // exact item with its independently issued provider reference.
        $status=$this->validatedState($evidence,$claimed);
        $this->settle($claimed,$status,$status==='verified_provider' ? $evidence['provider_reference'] : null);
        return $claimed->fresh();
    }

    public function requestCompensation(User $staff, TripAssemblyStep $step): TripAssemblyStep
    {
        return DB::transaction(function () use ($step,$staff): TripAssemblyStep {
            $current=TripAssemblyStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($current->status==='preexisting_stay') {
                throw ValidationException::withMessages(['step'=>'Cancel accommodation independently through its own workflow.']);
            }
            if (! in_array($current->status,['verified_provider','needs_provider','reconciliation_required'],true)) {
                throw ValidationException::withMessages(['step'=>'Compensation cannot begin in this state.']);
            }
            $before=$current->status;
            $current->update(['status'=>'compensation_requested']);
            DB::table('trip_assembly_events')->insert([
                'trip_assembly_id'=>$current->trip_assembly_id,'actor_id'=>$staff->id,
                'action'=>'compensation_requested','previous_status'=>$before,
                'new_status'=>'compensation_requested','created_at'=>now(),
            ]);
            // Only a contracted product adapter may perform external cancellation.
            // No refund/payment mutation and no false canceled status here.
            return $current;
        },3);
    }

    private function adapter(string $kind): ?TripProductRecoveryAdapter
    {
        if (! config('travel.trip_provider_recovery_enabled',false)) return null;
        $class=config('travel.trip_recovery_adapters.'.$kind);
        return is_string($class) && class_exists($class)
            && is_subclass_of($class,TripProductRecoveryAdapter::class)
            ? app($class) : null;
    }

    private function validatedState(mixed $evidence, TripAssemblyStep $step): string
    {
        if (! is_array($evidence) || ($evidence['verified']??null)!==true
            || ($evidence['item_id']??null)!==$step->item_id
            || ($evidence['item_type']??null)!==$step->item_type) {
            return 'reconciliation_required';
        }
        if (($evidence['state']??null)==='cancelled') return 'provider_cancelled';
        if (($evidence['state']??null)==='confirmed'
            && is_string($evidence['provider_reference']??null)
            && preg_match('/^[A-Za-z0-9_.:-]{5,160}$/D',$evidence['provider_reference'])) {
            return 'verified_provider';
        }
        return 'reconciliation_required';
    }

    private function settle(TripAssemblyStep $step,string $status,?string $reference): void
    {
        DB::transaction(function () use ($step,$status,$reference): void {
            $current=TripAssemblyStep::query()->whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($current->status!=='checking') return;
            $before=$current->status;
            $current->update(['status'=>$status,'provider_reference'=>$reference]);
            DB::table('trip_assembly_events')->insert([
                'trip_assembly_id'=>$current->trip_assembly_id,'actor_id'=>null,
                'action'=>'supplier_reconciled','previous_status'=>$before,
                'new_status'=>$status,'details'=>json_encode([
                    'component_type'=>$current->item_type,
                    'has_verified_reference'=>$reference !== null,
                ], JSON_THROW_ON_ERROR),'created_at'=>now(),
            ]);
        },3);
    }
}
