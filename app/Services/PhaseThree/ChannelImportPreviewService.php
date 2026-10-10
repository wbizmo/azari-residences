<?php

namespace App\Services\PhaseThree;

use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Channels\ICalChannelAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Owner-facing, side-effect-free iCal snapshot diff and oversell diagnostics.
 * Does not approve official provider API access or alter live inventory.
 */
final class ChannelImportPreviewService
{
    public function __construct(
        private readonly ICalChannelAdapter $ical,
        private readonly AzariAvailabilityEngine $inventory,
        private readonly ChannelMappingAuditService $health
    ) {}

    public function preview(ChannelConnection $connection, string $calendar): array
    {
        if ($connection->provider !== 'ical' || ! $connection->is_active
            || ! $connection->accommodation_type_id || ! $connection->accommodationType
            || (int) $connection->accommodationType->property_id !== (int) $connection->property_id) {
            throw ValidationException::withMessages(['mapping'=>'A live property-owned iCal room mapping is required.']);
        }
        $events = $this->ical->parse($calendar);
        if (count($events) > 1000) {
            throw ValidationException::withMessages(['calendar'=>'Preview supports at most 1,000 supplier events.']);
        }
        $existing = ChannelReservation::query()->where('channel_connection_id',$connection->id)
            ->get(['external_id','status','starts_on','ends_on','quantity'])->keyBy('external_id');
        $seen = []; $added = 0; $changed = 0; $cancelled = 0;
        $today = CarbonImmutable::today(); $horizon = $today->addDays(90);
        $supplierByDate = [];
        foreach ($events as $event) {
            $id = $event['external_id'];
            if (isset($seen[$id])) throw ValidationException::withMessages(['calendar'=>'Duplicate external reservation IDs.']);
            $seen[$id] = true;
            $prior = $existing->get($id);
            if (! $prior) $added++;
            elseif ($prior->status !== $event['status']
                || $prior->starts_on?->toDateString() !== $event['starts_on']
                || $prior->ends_on?->toDateString() !== $event['ends_on']
                || (int) $prior->quantity !== (int) $event['quantity']) $changed++;
            if ($event['status'] !== 'active') continue;
            $start = CarbonImmutable::parse($event['starts_on'])->max($today);
            $end = CarbonImmutable::parse($event['ends_on'])->min($horizon);
            for ($cursor = $start; $cursor->lt($end); $cursor = $cursor->addDay()) {
                $key = $cursor->toDateString();
                $supplierByDate[$key] = ($supplierByDate[$key] ?? 0) + (int) $event['quantity'];
            }
        }
        foreach ($existing as $id => $reservation) {
            if ($reservation->status === 'active' && ! isset($seen[$id])) $cancelled++;
        }
        $committed = $this->inventory->committedQuantityByDate($connection->accommodationType, $today, $horizon);
        $capacity = max(0, (int) $connection->accommodationType->total_inventory);
        $overbooked = [];
        foreach ($supplierByDate as $date => $quantity) {
            if ($quantity + (int) $committed->get($date,0) > $capacity) $overbooked[] = $date;
        }
        // Empty snapshots and missing reservations require manual supplier
        // confirmation; previews never release local inventory.
        $issues = $this->health->inspect($connection)['issues'];
        if ($events === [] && $existing->where('status','active')->isNotEmpty()) {
            $issues[] = 'Empty feed cannot release external reservations without manual confirmation.';
        }
        if ($cancelled > 0) $issues[] = 'Missing reservations must satisfy the supplier reconciliation grace period.';
        if ($overbooked !== []) $issues[] = 'External import would exceed available inventory on some dates.';
        return [
            'provider'=>'ical', 'snapshot_events'=>count($events),
            'new_reservations'=>$added, 'changed_reservations'=>$changed,
            'missing_active_reservations'=>$cancelled,
            'potential_oversell_dates'=>array_slice($overbooked,0,20),
            'potential_oversell_count'=>count($overbooked),
            'can_import_without_operator_review'=>$issues === [],
            'issues'=>$issues, 'applied'=>false,
        ];
    }
}
