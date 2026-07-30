<?php

namespace App\Observers;

use App\Models\OwnerPayoutProfile;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class OwnerPayoutProfileObserver
{
    use DispatchesAfterCommit;

    public function updated(OwnerPayoutProfile $profile): void
    {
        if (! $profile->wasChanged('is_verified')) {
            return;
        }

        $id = $profile->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = OwnerPayoutProfile::query()->with('user')->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->payoutProfileUpdated($fresh);
            }
        });
    }
}
