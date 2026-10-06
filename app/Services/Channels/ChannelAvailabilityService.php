<?php

namespace App\Services\Channels;

use App\Models\AccommodationType;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ChannelAvailabilityService
{
    public function blockedByDate(AccommodationType $type, CarbonInterface $in, CarbonInterface $out): Collection
    {
        $start = CarbonImmutable::parse($in->toDateString());
        $end = CarbonImmutable::parse($out->toDateString());
        $dates = collect();
        for ($cursor = $start; $cursor->lt($end); $cursor = $cursor->addDay()) {
            $dates->put($cursor->toDateString(), 0);
        }

        if ($dates->isEmpty() || ! Schema::hasTable('channel_connections') || ! Schema::hasTable('channel_reservations')) {
            return $dates;
        }

        $connections = ChannelConnection::query()
            ->where('property_id', $type->property_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('accommodation_type_id')->orWhere('accommodation_type_id', $type->id))
            ->get();

        if ($connections->contains(fn (ChannelConnection $connection) => $connection->fail_closed && $this->isStale($connection))) {
            return $dates->map(fn () => max(1, (int) $type->total_inventory));
        }

        $connectionIds = $connections->pluck('id');
        if ($connectionIds->isEmpty()) return $dates;

        $reservations = ChannelReservation::query()
            ->whereIn('channel_connection_id', $connectionIds)
            ->where('status', 'active')
            ->whereDate('starts_on', '<', $end->toDateString())
            ->whereDate('ends_on', '>', $start->toDateString())
            ->get(['starts_on', 'ends_on', 'quantity']);

        foreach ($reservations as $reservation) {
            $from = CarbonImmutable::parse($reservation->starts_on)->max($start);
            $until = CarbonImmutable::parse($reservation->ends_on)->min($end);
            for ($cursor = $from; $cursor->lt($until); $cursor = $cursor->addDay()) {
                $key = $cursor->toDateString();
                $dates->put($key, (int) $dates->get($key, 0) + max(1, (int) $reservation->quantity));
            }
        }
        return $dates;
    }

    public function isStale(ChannelConnection $connection): bool
    {
        if (! $connection->last_successful_sync_at) return true;
        return $connection->last_successful_sync_at->lt(now()->subMinutes(max(5, (int) $connection->stale_after_minutes)));
    }
}
