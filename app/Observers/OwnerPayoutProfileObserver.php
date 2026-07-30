<?php

namespace App\Observers;

use App\Models\OwnerPayoutProfile;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class OwnerPayoutProfileObserver
{
    use DispatchesAfterCommit;

    private const DESTINATION_FIELDS = [
        'preferred_gateway',
        'paypal_recipient',
        'paypal_recipient_type',
        'stripe_connected_account_id',
    ];

    public function created(OwnerPayoutProfile $profile): void
    {
        if (blank($profile->preferred_gateway)) {
            return;
        }

        $this->dispatch($profile->getKey(), destinationChanged: true, verificationChanged: false);
    }

    public function updated(OwnerPayoutProfile $profile): void
    {
        $destinationChanged = $profile->wasChanged(self::DESTINATION_FIELDS);
        $verificationChanged = $profile->wasChanged('is_verified');

        if (! $destinationChanged && ! $verificationChanged) {
            return;
        }

        $this->dispatch($profile->getKey(), $destinationChanged, $verificationChanged);
    }

    private function dispatch(int|string $id, bool $destinationChanged, bool $verificationChanged): void
    {
        $this->afterCommit(function () use ($id, $destinationChanged, $verificationChanged): void {
            $fresh = OwnerPayoutProfile::query()->with('user')->find($id);
            if (! $fresh) {
                return;
            }

            $mail = app(AzariTransactionalMailService::class);

            if ($destinationChanged) {
                $mail->payoutDestinationChanged($fresh);

                return;
            }

            if ($verificationChanged) {
                $mail->payoutProfileUpdated($fresh);
            }
        });
    }
}
