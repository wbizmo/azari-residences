<?php

namespace App\Observers;

use App\Models\WithdrawalRequest;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class WithdrawalRequestObserver
{
    use DispatchesAfterCommit;

    public function created(WithdrawalRequest $withdrawal): void
    {
        $id = $withdrawal->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = WithdrawalRequest::query()->with('user')->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->withdrawalCreated($fresh);
            }
        });
    }

    public function updated(WithdrawalRequest $withdrawal): void
    {
        if (! $withdrawal->wasChanged('status')) {
            return;
        }

        $id = $withdrawal->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = WithdrawalRequest::query()->with('user')->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->withdrawalUpdated($fresh);
            }
        });
    }
}
