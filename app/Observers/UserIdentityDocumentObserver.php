<?php

namespace App\Observers;

use App\Models\UserIdentityDocument;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class UserIdentityDocumentObserver
{
    use DispatchesAfterCommit;

    public function updated(UserIdentityDocument $document): void
    {
        if (! $document->wasChanged('review_status')) {
            return;
        }

        $id = $document->getKey();

        $this->afterCommit(function () use ($id): void {
            $fresh = UserIdentityDocument::query()->with(['user', 'identityType'])->find($id);
            if ($fresh) {
                app(AzariTransactionalMailService::class)->userIdentityReviewed($fresh);
            }
        });
    }
}
