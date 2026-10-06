<?php

namespace App\Observers;

use App\Models\BookingModificationRequest;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class BookingModificationRequestObserver
{
    use DispatchesAfterCommit;

    public function created(BookingModificationRequest $request): void
    {
        $this->dispatch($request->getKey(), 'created');
    }

    public function updated(BookingModificationRequest $request): void
    {
        if ($request->wasChanged('status') || $request->wasChanged('staff_note')) {
            $this->dispatch($request->getKey(), 'updated');
        }
    }

    private function dispatch(int $id, string $event): void
    {
        $this->afterCommit(function () use ($id, $event): void {
            $fresh = BookingModificationRequest::query()
                ->with(['booking.property', 'booking.user', 'user'])
                ->find($id);

            if ($fresh) {
                app(AzariTransactionalMailService::class)->bookingModificationUpdated($fresh, $event);
            }
        });
    }
}
