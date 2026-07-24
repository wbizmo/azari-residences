<?php
namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\MaintenancePeriod;
use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AzariAvailabilityEngine {
    public function assertRules(Property $property, CarbonInterface $in, CarbonInterface $out, int $adults, int $children, int $rooms=1): void {
        $nights = $in->diffInDays($out);
        if ($out->lessThanOrEqualTo($in)) throw ValidationException::withMessages(['check_out'=>'Check-out must be after check-in.']);
        if ($in->isBefore(today())) throw ValidationException::withMessages(['check_in'=>'Check-in cannot be in the past.']);
        if (!($property->same_day_booking ?? config('azari.booking.same_day_booking')) && $in->isSameDay(today())) {
            throw ValidationException::withMessages(['check_in'=>'Same-day booking is unavailable for this residence.']);
        }
        $min = max(1,(int)($property->minimum_stay ?? 1));
        $max = $property->maximum_stay ? (int)$property->maximum_stay : null;
        if ($nights < $min) throw ValidationException::withMessages(['check_out'=>"Minimum stay is {$min} night(s)."]);
        if ($max && $nights > $max) throw ValidationException::withMessages(['check_out'=>"Maximum stay is {$max} night(s)."]);
        $capacity = max(1,(int)($property->max_guests ?? 1))*max(1,$rooms);
        if (($adults+$children)>$capacity) throw ValidationException::withMessages(['adults'=>"Maximum capacity is {$capacity} guest(s)."]);
    }

    public function available(int $propertyId, CarbonInterface $in, CarbonInterface $out, ?int $ignoreBooking=null, ?string $ignoreHold=null): bool {
        if (Booking::query()->where('property_id',$propertyId)->whereIn('status',config('azari.booking.active_statuses'))
            ->when($ignoreBooking,fn(Builder $q)=>$q->whereKeyNot($ignoreBooking))
            ->whereDate('check_in','<',$out)->whereDate('check_out','>',$in)->exists()) return false;

        if (BookingHold::query()->active()->where('property_id',$propertyId)
            ->when($ignoreHold,fn(Builder $q)=>$q->where('token','!=',$ignoreHold))
            ->whereDate('check_in','<',$out)->whereDate('check_out','>',$in)->exists()) return false;

        return !MaintenancePeriod::query()->where('property_id',$propertyId)->where('blocks_booking',true)
            ->whereDate('starts_on','<',$out)->whereDate('ends_on','>',$in)->exists();
    }

    public function hold(Property $property, CarbonInterface $in, CarbonInterface $out, int $adults, int $children, int $rooms, ?int $userId): BookingHold {
        $this->assertRules($property,$in,$out,$adults,$children,$rooms);
        return DB::transaction(function() use($property,$in,$out,$adults,$children,$rooms,$userId) {
            BookingHold::query()->where('expires_at','<=',now())->delete();
            if (!$this->available($property->getKey(),$in,$out)) throw ValidationException::withMessages(['property_id'=>'Residence is no longer available.']);
            return BookingHold::query()->create([
                'property_id'=>$property->getKey(),'user_id'=>$userId,'check_in'=>$in,'check_out'=>$out,
                'adults'=>$adults,'children'=>$children,'rooms'=>$rooms,
                'expires_at'=>now()->addMinutes(config('azari.booking.hold_minutes',15)),
            ]);
        },3);
    }
}
