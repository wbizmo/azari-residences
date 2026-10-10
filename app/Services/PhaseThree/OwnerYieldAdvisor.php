<?php
namespace App\Services\PhaseThree;

use App\Models\AccommodationType;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Evidence-based suggestions only; never changes existing prices or confirmed invoices. */
final class OwnerYieldAdvisor
{
    public function preview(AccommodationType $type, CarbonImmutable $from, CarbonImmutable $to, int $minimum = 0, int $maximum = 0): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        if ($to->lessThan($from) || $days > 31 || $from->isPast()) {
            throw ValidationException::withMessages(['date'=>'Choose between 1 and 31 future dates.']);
        }
        $capacity = max(1, (int) $type->total_inventory);
        $base = (float) $type->base_rate;
        $min = $minimum > 0 ? $minimum : (int) floor($base * 0.8);
        $max = $maximum > 0 ? $maximum : (int) ceil($base * 1.2);
        if ($min > $max || $min < 0) {
            throw ValidationException::withMessages(['price'=>'Price floor must not exceed ceiling.']);
        }
        $bookings = Booking::query()->where('property_id', $type->property_id)
            ->where('accommodation_type_id', $type->id)
            ->whereIn('status', ['confirmed','paid','check_in','checked_in'])
            ->where('check_in', '<', $to->addDay()->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->get(['check_in','check_out','rooms']);
        $nights = 0;
        foreach ($bookings as $booking) {
            $start = max($from->toDateString(), $booking->check_in->toDateString());
            $end = min($to->addDay()->toDateString(), $booking->check_out->toDateString());
            $nights += (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end))
                * max(1, (int) $booking->rooms);
        }
        $oversold = $nights > $capacity * $days;
        $occupancy = min(1, $nights / ($capacity * $days));
        $eligible = ! $oversold && $bookings->count() >= 5 && $days >= 7;
        // The low sample-size gate avoids manufacturing certainty and prices.
        $multiplier = $occupancy >= 0.8 ? 1.05 : ($occupancy <= 0.3 ? 0.97 : 1.0);
        return [
            'date_from'=>$from->toDateString(), 'date_to'=>$to->toDateString(),
            'booked_room_nights'=>$nights, 'capacity_room_nights'=>$capacity*$days,
            'occupancy_ratio'=>round($occupancy, 4), 'sample_bookings'=>$bookings->count(),
            'eligible'=>$eligible, 'currency'=>$type->currency,
            'current_base_rate'=>$base,
            'suggested_rate'=>$eligible ? max($min, min($max, round($base*$multiplier, 2))) : null,
            'reason'=>$eligible ? 'Bounded recommendation from confirmed booked room nights; owner approval required.'
                : ($oversold ? 'Overcommitted room nights require inventory reconciliation before price advice.'
                    : 'Insufficient confirmed demand evidence; no price recommendation.'),
            'applied'=>false,
        ];
    }
}
