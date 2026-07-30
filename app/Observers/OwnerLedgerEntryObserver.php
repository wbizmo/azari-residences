<?php

namespace App\Observers;

use App\Models\OwnerLedgerEntry;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class OwnerLedgerEntryObserver
{
    use DispatchesAfterCommit;

    public function created(OwnerLedgerEntry $entry): void
    {
        if ($entry->type !== 'booking_earning' || $entry->direction !== 'credit') {
            return;
        }

        $id = $entry->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = OwnerLedgerEntry::query()->with(['user', 'property', 'booking'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->ownerEarningCredited($fresh);
            }
        });
    }
}
