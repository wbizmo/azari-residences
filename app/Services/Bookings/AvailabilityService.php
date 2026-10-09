<?php
namespace App\Services\Bookings;
use App\Models\Property;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
class AvailabilityService
{
    public function __construct(private readonly AzariAvailabilityEngine $engine) {}

    public function isAvailable(int $propertyId, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $ignoreBookingId = null): bool
    {
        $property = Property::query()->find($propertyId);

        return $property !== null
            && $this->engine->availableForProperty($property, $checkIn, $checkOut, 1, null, $ignoreBookingId);
    }

    public function quote(int $propertyId, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $nights = max(1, $checkIn->diffInDays($checkOut));
        $season = DB::table('seasonal_prices')->where('property_id', $propertyId)
            ->whereDate('starts_on', '<=', $checkIn)
            ->whereDate('ends_on', '>=', $checkOut->copy()->subDay())
            ->orderByDesc('starts_on')->first();
        $rate = (float)($season->nightly_rate ?? 0);
        return ['available'=>$this->isAvailable($propertyId,$checkIn,$checkOut),'nights'=>$nights,'nightly_rate'=>$rate,'subtotal'=>$rate*$nights,'currency'=>'USD','minimum_stay'=>(int)($season->minimum_stay ?? 1),'maximum_stay'=>$season?->maximum_stay];
    }
}
