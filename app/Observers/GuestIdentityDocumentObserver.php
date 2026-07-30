<?php

namespace App\Observers;

use App\Models\GuestIdentityDocument;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class GuestIdentityDocumentObserver
{
    use DispatchesAfterCommit;

    public function updated(GuestIdentityDocument $document): void
    {
        if (! $document->wasChanged('review_status')) {
            return;
        }

        $id = $document->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = GuestIdentityDocument::query()
                ->with(['guest.booking.user', 'guest.booking.property'])
                ->find($id);

            if ($fresh) {
                app(AzariTransactionalMailService::class)->guestIdentityReviewed($fresh);
            }
        });
    }
}
