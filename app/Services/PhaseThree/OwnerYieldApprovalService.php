<?php

namespace App\Services\PhaseThree;

use App\Models\AccommodationType;
use App\Models\InventoryChangeLog;
use App\Services\Bookings\InventoryBulkUpdateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Accept an exact owner-reviewed suggestion via the audited calendar. */
final class OwnerYieldApprovalService
{
    public function __construct(
        private readonly OwnerYieldAdvisor $advisor,
        private readonly InventoryBulkUpdateService $inventory
    ) {}

    public function preview(AccommodationType $type, CarbonImmutable $from, CarbonImmutable $to,
        int $floor = 0, int $ceiling = 0): array
    {
        $result = $this->advisor->preview($type, $from, $to, $floor, $ceiling);
        $result['revision'] = $this->inventory->revision($type, $from, $to);
        return $result;
    }

    public function approve(AccommodationType $type, CarbonImmutable $from, CarbonImmutable $to,
        int $floor, int $ceiling, string $revision, string $acceptedRate,
        int $actorId): InventoryChangeLog
    {
        if (! preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D', $acceptedRate)) {
            throw ValidationException::withMessages(['accepted_rate' => 'Invalid rate precision.']);
        }
        return DB::transaction(function () use ($type, $from, $to, $floor, $ceiling,
            $revision, $acceptedRate, $actorId): InventoryChangeLog {
            // Bulk-update rechecks the same revision inside canonical property
            // then room locks; stale booking/inventory updates cannot slip in.
            $preview = $this->preview($type, $from, $to, $floor, $ceiling);
            if (! $preview['eligible'] || $preview['suggested_rate'] === null
                || ! hash_equals($preview['revision'], $revision)
                || abs((float) $acceptedRate - (float) $preview['suggested_rate']) > 0.00001) {
                throw ValidationException::withMessages([
                    'accepted_rate' => 'Recommendation changed. Review the latest demand and inventory again.',
                ]);
            }
            return $this->inventory->apply($type, $from, $to,
                ['price_override' => $acceptedRate], $actorId, 'owner_yield_approved', $revision);
        }, 3);
    }
}
