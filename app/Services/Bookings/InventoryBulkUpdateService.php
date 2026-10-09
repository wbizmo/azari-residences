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
        string $source = 'admin'
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

        return DB::transaction(function () use ($type, $from, $to, $changes, $actorId, $source): InventoryChangeLog {
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
