<?php

namespace App\Observers;

use App\Models\PropertyListing;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class PropertyListingObserver
{
    use DispatchesAfterCommit;

    public function created(PropertyListing $listing): void
    {
        $id = $listing->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = PropertyListing::query()->with(['user', 'approvedProperty'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->propertyListingCreated($fresh);
            }
        });
    }

    public function updated(PropertyListing $listing): void
    {
        if (! $listing->wasChanged('status')) {
            return;
        }

        $id = $listing->getKey();
        $from = (string) $listing->getOriginal('status');

        $this->afterCommit(function () use ($id, $from): void {
            $fresh = PropertyListing::query()->with(['user', 'approvedProperty'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->propertyListingUpdated($fresh, $from);
            }
        });
    }
}
