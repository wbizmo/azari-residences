<?php

namespace App\Services\Owners;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\InventoryDate;
use App\Services\Channels\ChannelAvailabilityService;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OwnerInventoryCalendarService
{
    public function __construct(private readonly ChannelAvailabilityService $channels) {}

    public function snapshot(AccommodationType $type, int $days = 30): Collection
    {
        $type->loadMissing('property');
        $timezone = LocalDate::propertyTimezone($type->property);
        $start = CarbonImmutable::now($timezone)->startOfDay();
        $days = max(7, min($days, 62));
        $end = $start->addDays($days);

        $dates = collect();
        for ($cursor = $start; $cursor->lt($end); $cursor = $cursor->addDay()) {
            $dates->put($cursor->toDateString(), [
                'date' => $cursor,
                'booked' => 0,
                'held' => 0,
                'external' => 0,
            ]);
        }

        $activeStatuses = config('azari.booking.active_statuses', [
            'hold', 'pending', 'pending_payment', 'approved', 'confirmed',
            'paid', 'check_in', 'checked_in',
        ]);

        $bookings = Booking::query()
            ->where('property_id', $type->property_id)
            ->where('accommodation_type_id', $type->id)
            ->whereIn('status', $activeStatuses)
            ->where(function ($query): void {
                $query->whereNotIn('status', ['pending', 'pending_payment'])
                    ->orWhere(function ($pending): void {
                        $pending->whereIn('status', ['pending', 'pending_payment'])
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            })
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['check_in', 'check_out', 'rooms']);

        $holds = BookingHold::query()
            ->active()
            ->where('property_id', $type->property_id)
            ->where('accommodation_type_id', $type->id)
            ->whereDate('check_in', '<', $end->toDateString())
            ->whereDate('check_out', '>', $start->toDateString())
            ->get(['check_in', 'check_out', 'rooms']);

        foreach ($bookings as $booking) {
            $this->addOccupancy($dates, $start, $end, $booking->check_in, $booking->check_out, 'booked', max(1, (int) $booking->rooms));
        }

        foreach ($holds as $hold) {
            $this->addOccupancy($dates, $start, $end, $hold->check_in, $hold->check_out, 'held', max(1, (int) $hold->rooms));
        }

        $external = $this->channels->blockedByDate($type, $start, $end);
        $inventory = InventoryDate::query()
            ->where('accommodation_type_id', $type->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->get()
            ->keyBy(fn (InventoryDate $row) => $row->date->toDateString());

        return $dates->map(function (array $row, string $date) use ($type, $external, $inventory): array {
            $override = $inventory->get($date);
            $sellable = $override?->sellable_inventory;
            $sellable = $sellable === null ? (int) $type->total_inventory : max(0, (int) $sellable);
            $maintenance = max(0, (int) ($override?->maintenance_inventory ?? 0));
            $externalCount = max(0, (int) $external->get($date, 0));
            $effectiveSellable = $override?->stop_sell ? 0 : $sellable;
            $available = max(0, $effectiveSellable - $maintenance - $row['booked'] - $row['held'] - $externalCount);

            return [
                ...$row,
                'sellable' => $sellable,
                'maintenance' => $maintenance,
                'external' => $externalCount,
                'available' => $available,
                'stop_sell' => (bool) ($override?->stop_sell ?? false),
                'minimum_stay' => $override?->minimum_stay,
                'price_override' => $override?->price_override,
            ];
        })->values();
    }

    private function addOccupancy(
        Collection $dates,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        $rawStart,
        $rawEnd,
        string $bucket,
        int $quantity
    ): void {
        $from = CarbonImmutable::parse($rawStart)->max($windowStart);
        $until = CarbonImmutable::parse($rawEnd)->min($windowEnd);

        for ($cursor = $from; $cursor->lt($until); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            if (! $dates->has($key)) continue;
            $row = $dates->get($key);
            $row[$bucket] += $quantity;
            $dates->put($key, $row);
        }
    }
}
