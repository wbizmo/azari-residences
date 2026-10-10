<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\InventoryDate;
use App\Models\Property;
use App\Services\Channels\ChannelAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OwnerInventoryCalendarService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly ChannelAvailabilityService $channels,
    ) {}

    /**
     * Render at most six weeks, with a bounded number of queries per room
     * type rather than one SQL query per room-night/cell.
     */
    public function forProperty(Property $property, CarbonImmutable $date, string $view): array
    {
        $view = $view === 'week' ? 'week' : 'month';
        $anchor = $view === 'week' ? $date->startOfWeek() : $date->startOfMonth();
        $start = $view === 'week'
            ? $anchor
            : $anchor->startOfWeek();
        $end = $view === 'week'
            ? $start->addWeek()
            : $anchor->endOfMonth()->startOfWeek()->addWeek();

        $days = [];
        for ($cursor = $start; $cursor->lt($end); $cursor = $cursor->addDay()) {
            $days[] = $cursor->toDateString();
        }

        $types = $property->accommodationTypes()
            ->orderBy('sort_order')->orderBy('id')->get();
        $primaryId = $types->first()?->id;
        $rows = [];

        foreach ($types as $type) {
            $inventory = InventoryDate::query()
                ->where('accommodation_type_id', $type->id)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<', $end->toDateString())
                ->get()->keyBy(fn (InventoryDate $item) => $item->date->toDateString());

            $bookings = Booking::query()
                ->where('property_id', $property->id)
                ->where(function ($query) use ($type, $primaryId): void {
                    $query->where('accommodation_type_id', $type->id);
                    if ((int) $primaryId === (int) $type->id) {
                        $query->orWhereNull('accommodation_type_id');
                    }
                })
                ->whereIn('status', config('azari.booking.active_statuses', [
                    'hold', 'pending', 'pending_payment', 'approved', 'confirmed',
                    'paid', 'check_in', 'checked_in',
                ]))
                ->where(function ($query): void {
                    $query->whereNotIn('status', ['pending', 'pending_payment'])
                        ->orWhere(fn ($pending) => $pending
                            ->whereIn('status', ['pending', 'pending_payment'])
                            ->whereNotNull('expires_at')->where('expires_at', '>', now()));
                })
                ->whereDate('check_in', '<', $end->toDateString())
                ->whereDate('check_out', '>', $start->toDateString())
                ->get(['check_in', 'check_out', 'rooms']);

            $holds = BookingHold::query()->active()
                ->where('property_id', $property->id)
                ->where(function ($query) use ($type, $primaryId): void {
                    $query->where('accommodation_type_id', $type->id);
                    if ((int) $primaryId === (int) $type->id) {
                        $query->orWhereNull('accommodation_type_id');
                    }
                })
                ->whereDate('check_in', '<', $end->toDateString())
                ->whereDate('check_out', '>', $start->toDateString())
                ->get(['check_in', 'check_out', 'rooms']);

            $booked = $this->countNights($bookings, $start, $end);
            $held = $this->countNights($holds, $start, $end);
            $external = $this->channels->blockedByDate($type, $start, $end);
            $remaining = $this->availability->remainingByDate($type, $start, $end);
            $cells = [];

            foreach ($days as $day) {
                $item = $inventory->get($day);
                $sellable = $item?->sellable_inventory;
                $cells[$day] = [
                    'sellable' => $sellable === null
                        ? (int) $type->total_inventory : (int) $sellable,
                    'maintenance' => max(0, (int) ($item?->maintenance_inventory ?? 0)),
                    'booked' => (int) $booked->get($day, 0),
                    'held' => (int) $held->get($day, 0),
                    'external' => (int) $external->get($day, 0),
                    'remaining' => (int) $remaining->get($day, 0),
                    'stop_sell' => (bool) ($item?->stop_sell ?? false),
                    'price_override' => $item?->price_override,
                ];
            }
            $rows[] = [
                'type' => $type,
                'dates' => $cells,
            ];
        }

        return [
            'view' => $view,
            'anchor' => $anchor->toDateString(),
            'previous' => ($view === 'week' ? $anchor->subWeek() : $anchor->subMonth())->toDateString(),
            'next' => ($view === 'week' ? $anchor->addWeek() : $anchor->addMonth())->toDateString(),
            'weeks' => collect($days)->chunk(7)->values()->all(),
            'types' => $rows,
        ];
    }

    private function countNights(Collection $bookings, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $events = [];
        foreach ($bookings as $booking) {
            $from = CarbonImmutable::parse($booking->check_in->toDateString())->startOfDay();
            $to = CarbonImmutable::parse($booking->check_out->toDateString())->startOfDay();
            $from = $from->lessThan($start) ? $start : $from;
            $to = $to->greaterThan($end) ? $end : $to;
            if ($from->greaterThanOrEqualTo($to)) {
                continue;
            }
            $first = $from->toDateString();
            $last = $to->toDateString();
            $quantity = max(1, (int) $booking->rooms);
            $events[$first] = ($events[$first] ?? 0) + $quantity;
            $events[$last] = ($events[$last] ?? 0) - $quantity;
        }

        $totals = collect();
        $count = 0;
        for ($day = $start; $day->lt($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $count += (int) ($events[$key] ?? 0);
            $totals->put($key, max(0, $count));
        }

        return $totals;
    }
}
