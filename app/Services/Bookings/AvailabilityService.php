<?php
namespace App\Services\Bookings;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
class AvailabilityService
{
    public function isAvailable(int $propertyId, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $ignoreBookingId = null): bool
    {
        if ($checkOut->lessThanOrEqualTo($checkIn)) return false;
        $bookingConflict = Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', ['pending','approved','confirmed','checked_in'])
            ->when($ignoreBookingId, fn($q) => $q->whereKeyNot($ignoreBookingId))
            ->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn)
            ->exists();
        if ($bookingConflict) return false;
        return ! DB::table('blocked_dates')
            ->where('property_id', $propertyId)
            ->whereDate('starts_on', '<', $checkOut)
            ->whereDate('ends_on', '>', $checkIn)
            ->exists();
    }

    public function quote(int $propertyId, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $season = DB::table('seasonal_prices')->where('property_id', $propertyId)
            ->whereDate('starts_on', '<=', $checkIn)
            ->whereDate('ends_on', '>=', $checkOut->copy()->subDay())
            ->orderByDesc('starts_on')->first();
        $rate = (float)($season->nightly_rate ?? 0);
        return ['available'=>$this->isAvailable($propertyId,$checkIn,$checkOut),'nights'=>$nights,'nightly_rate'=>$rate,'subtotal'=>$rate*$nights,'currency'=>'NGN','minimum_stay'=>(int)($season->minimum_stay ?? 1),'maximum_stay'=>$season?->maximum_stay];
    }
}
