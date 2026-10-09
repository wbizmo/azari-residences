<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\InventoryDate;
use App\Models\Property;
use App\Models\InventoryChangeLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryBulkUpdateService
{
    public function __construct(private readonly AzariAvailabilityEngine $availability) {}

    private const ALLOWED = [
        'sellable_inventory', 'maintenance_inventory', 'stop_sell',
        'closed_to_arrival', 'closed_to_departure', 'minimum_stay',
        'maximum_stay', 'price_override',
    ];

    public function apply(
        AccommodationType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $changes,
        ?int $actorId,
        string $source = 'admin',
        ?string $expectedVersion = null
    ): InventoryChangeLog {
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['to_date' => 'End date must be on or after the start date.']);
        }

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to_date' => 'Bulk inventory updates are limited to 367 calendar days at a time.']);
        }

        $changes = collect($changes)->only(self::ALLOWED)->all();

        if ($changes === []) {
            throw ValidationException::withMessages(['inventory' => 'Choose at least one inventory or rate field to update.']);
        }

        $this->validateChanges($type, $changes);

        return DB::transaction(function () use ($type, $from, $to, $changes, $actorId, $source, $expectedVersion): InventoryChangeLog {
            // Match the hold lock ordering: property, accommodation type, date range.
            Property::query()->whereKey($type->property_id)
                ->when(DB::connection()->getDriverName() !== 'sqlite', fn (Builder $query) => $query->lockForUpdate())
                ->firstOrFail();

            $lockedType = AccommodationType::query()
                ->whereKey($type->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $this->assertCommittedInventoryPreserved($lockedType, $from, $to, $changes);

            $existingRows = InventoryDate::query()
                ->where('accommodation_type_id', $lockedType->getKey())
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->orderBy('date')
                ->get()
                ->keyBy(fn (InventoryDate $row) => $row->date->toDateString());

            $currentVersion = $this->versionFromRows($existingRows, $from, $to);
            if ($expectedVersion !== null && ! hash_equals($expectedVersion, $currentVersion)) {
                throw ValidationException::withMessages([
                    'inventory' => 'Calendar data changed after your preview. Review the latest values before applying this update.',
                ]);
            }

            $beforeSnapshot = [];
            for ($snapshotDate = $from; $snapshotDate->lessThanOrEqualTo($to); $snapshotDate = $snapshotDate->addDay()) {
                $key = $snapshotDate->toDateString();
                $row = $existingRows->get($key);
                $beforeSnapshot[$key] = [
                    'exists' => (bool) $row,
                    'sellable_inventory' => $row?->sellable_inventory,
                    'maintenance_inventory' => $row?->maintenance_inventory,
                    'stop_sell' => $row?->stop_sell,
                    'closed_to_arrival' => $row?->closed_to_arrival,
                    'closed_to_departure' => $row?->closed_to_departure,
                    'minimum_stay' => $row?->minimum_stay,
                    'maximum_stay' => $row?->maximum_stay,
                    'price_override' => $row?->price_override,
                ];
            }

            $rows = [];
            for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
                $rows[] = array_merge([
                    'accommodation_type_id' => $lockedType->getKey(),
                    'date' => $date->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $changes);
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('inventory_dates')->upsert(
                    $chunk,
                    ['accommodation_type_id', 'date'],
                    [...array_keys($changes), 'updated_at']
                );
            }

            return InventoryChangeLog::query()->create([
                'property_id' => $lockedType->property_id,
                'accommodation_type_id' => $lockedType->getKey(),
                'actor_id' => $actorId,
                'from_date' => $from,
                'to_date' => $to,
                'source' => $source,
                'changes' => $changes,
                'before_snapshot' => $beforeSnapshot,
            ]);
        }, 5);
    }

    /** Prevent rate/allotment bulk edits from removing rooms already committed to stays. */
    private function assertCommittedInventoryPreserved(
        AccommodationType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $changes
    ): void {
        if (! array_key_exists('sellable_inventory', $changes)
            && ! array_key_exists('maintenance_inventory', $changes)) {
            return;
        }

        $dates = InventoryDate::query()
            ->where('accommodation_type_id', $type->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->when(DB::connection()->getDriverName() !== 'sqlite', fn (Builder $query) => $query->lockForUpdate())
            ->get()
            ->keyBy(fn (InventoryDate $row) => $row->date->toDateString());

        $committed = $this->availability->committedQuantityByDate($type, $from, $to->addDay());

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $existing = $dates->get($key);
            $sellable = array_key_exists('sellable_inventory', $changes)
                ? $changes['sellable_inventory']
                : $existing?->sellable_inventory;
            $sellable = $sellable === null ? (int) $type->total_inventory : (int) $sellable;
            $maintenance = array_key_exists('maintenance_inventory', $changes)
                ? (int) ($changes['maintenance_inventory'] ?? 0)
                : (int) ($existing?->maintenance_inventory ?? 0);
            $reserved = (int) $committed->get($key, 0);

            if ($sellable - $maintenance < $reserved) {
                throw ValidationException::withMessages([
                    'sellable_inventory' => "Cannot reduce rooms below {$reserved} committed unit(s) on {$key}.",
                ]);
            }
        }
    }


    public function preview(
        AccommodationType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $changes
    ): array {
        if ($to->lessThan($from) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to_date' => 'Preview range must be between 1 and 367 calendar days.']);
        }

        $changes = collect($changes)->only(self::ALLOWED)->all();
        $this->validateChanges($type, $changes);
        $this->assertCommittedInventoryPreserved($type, $from, $to, $changes);

        $committed = $this->availability->committedQuantityByDate($type, $from, $to->addDay());

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $from->diffInDays($to) + 1,
            'changes' => $changes,
            'maximum_committed_units' => (int) ($committed->max() ?? 0),
            'affected_committed_dates' => $committed->filter(fn ($count) => (int) $count > 0)->count(),
            'version' => $this->currentVersion($type, $from, $to),
        ];
    }

    public function undo(InventoryChangeLog $log, ?int $actorId): InventoryChangeLog
    {
        if ($log->reverted_at) {
            throw ValidationException::withMessages(['inventory' => 'This calendar change has already been undone.']);
        }

        $snapshot = (array) $log->before_snapshot;
        if ($snapshot === []) {
            throw ValidationException::withMessages(['inventory' => 'This older calendar change does not contain an undo snapshot.']);
        }

        return DB::transaction(function () use ($log, $snapshot, $actorId): InventoryChangeLog {
            $lockedLog = InventoryChangeLog::query()->whereKey($log->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite', fn (Builder $q) => $q->lockForUpdate())
                ->firstOrFail();

            if ($lockedLog->reverted_at) {
                throw ValidationException::withMessages(['inventory' => 'This calendar change has already been undone.']);
            }

            $type = AccommodationType::query()->whereKey($lockedLog->accommodation_type_id)
                ->when(DB::connection()->getDriverName() !== 'sqlite', fn (Builder $q) => $q->lockForUpdate())
                ->firstOrFail();

            foreach ($snapshot as $date => $before) {
                if (! ($before['exists'] ?? false)) {
                    InventoryDate::query()
                        ->where('accommodation_type_id', $type->id)
                        ->whereDate('date', $date)
                        ->delete();
                    continue;
                }

                $restore = collect($before)->only(self::ALLOWED)->all();
                $this->assertCommittedInventoryPreserved(
                    $type,
                    CarbonImmutable::parse($date),
                    CarbonImmutable::parse($date),
                    $restore
                );

                InventoryDate::query()->updateOrCreate(
                    ['accommodation_type_id' => $type->id, 'date' => $date],
                    $restore
                );
            }

            $lockedLog->update(['reverted_at' => now(), 'reverted_by' => $actorId]);

            return $lockedLog->fresh();
        }, 5);
    }

    private function currentVersion(
        AccommodationType $type,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): string {
        $rows = InventoryDate::query()
            ->where('accommodation_type_id', $type->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (InventoryDate $row) => $row->date->toDateString());

        return $this->versionFromRows($rows, $from, $to);
    }

    private function versionFromRows(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $snapshot = [];
        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);
            $snapshot[$key] = $row ? [
                'sellable_inventory' => $row->sellable_inventory,
                'maintenance_inventory' => $row->maintenance_inventory,
                'stop_sell' => (bool) $row->stop_sell,
                'closed_to_arrival' => (bool) $row->closed_to_arrival,
                'closed_to_departure' => (bool) $row->closed_to_departure,
                'minimum_stay' => $row->minimum_stay,
                'maximum_stay' => $row->maximum_stay,
                'price_override' => $row->price_override,
                'updated_at' => optional($row->updated_at)->toISOString(),
            ] : null;
        }

        return hash('sha256', json_encode($snapshot, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES));
    }

    private function validateChanges(AccommodationType $type, array $changes): void
    {
        foreach (['sellable_inventory', 'maintenance_inventory', 'minimum_stay', 'maximum_stay'] as $key) {
            if (array_key_exists($key, $changes) && $changes[$key] !== null && (int) $changes[$key] < 0) {
                throw ValidationException::withMessages([$key => 'Inventory and stay values cannot be negative.']);
            }
        }

        if (isset($changes['sellable_inventory']) && (int) $changes['sellable_inventory'] > (int) $type->total_inventory) {
            throw ValidationException::withMessages([
                'sellable_inventory' => 'Sellable inventory cannot exceed the accommodation type total inventory.',
            ]);
        }

        if (
            isset($changes['minimum_stay'], $changes['maximum_stay'])
            && $changes['maximum_stay'] !== null
            && (int) $changes['maximum_stay'] < (int) $changes['minimum_stay']
        ) {
            throw ValidationException::withMessages(['maximum_stay' => 'Maximum stay cannot be less than minimum stay.']);
        }

        if (array_key_exists('price_override', $changes) && $changes['price_override'] !== null && (float) $changes['price_override'] < 0) {
            throw ValidationException::withMessages(['price_override' => 'Price cannot be negative.']);
        }
    }
}
