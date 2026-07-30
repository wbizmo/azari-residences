<?php

namespace App\Observers;

use App\Models\ServiceRequest;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class ServiceRequestObserver
{
    use DispatchesAfterCommit;

    public function created(ServiceRequest $serviceRequest): void
    {
        $id = $serviceRequest->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = ServiceRequest::query()->with(['booking.property', 'user'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->serviceRequestCreated($fresh);
            }
        });
    }

    public function updated(ServiceRequest $serviceRequest): void
    {
        if (! $serviceRequest->wasChanged(['status', 'guest_reply', 'assigned_to'])) {
            return;
        }

        $id = $serviceRequest->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = ServiceRequest::query()->with(['booking.property', 'user'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->serviceRequestUpdated($fresh);
            }
        });
    }
}
