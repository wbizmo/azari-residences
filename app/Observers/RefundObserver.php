<?php

namespace App\Observers;

use App\Models\Refund;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class RefundObserver
{
    use DispatchesAfterCommit;

    public function created(Refund $refund): void
    {
        $this->dispatch($refund->getKey(), 'created');
    }

    public function updated(Refund $refund): void
    {
        if ($refund->wasChanged('status')) {
            $this->dispatch($refund->getKey(), 'updated');
        }
    }

    private function dispatch(int $id, string $event): void
    {
        $this->afterCommit(function () use ($id, $event): void {
            $fresh = Refund::query()
                ->with(['booking.property', 'booking.user', 'payment'])
                ->find($id);

            if ($fresh) {
                app(AzariTransactionalMailService::class)->refundUpdated($fresh, $event);
            }
        });
    }
}
