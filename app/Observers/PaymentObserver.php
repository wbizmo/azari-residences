<?php

namespace App\Observers;

use App\Models\Payment;
use App\Observers\Concerns\DispatchesAfterCommit;
use App\Services\Communications\AzariTransactionalMailService;

class PaymentObserver
{
    use DispatchesAfterCommit;

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $id = $payment->getKey();
        $status = (string) $payment->status;

        $this->afterCommit(function () use ($id, $status): void {
            $fresh = Payment::query()->with(['booking.property', 'booking.user'])->find($id);
            if (! $fresh) {
                return;
            }

            $mail = app(AzariTransactionalMailService::class);

            match ($status) {
                'pending' => $mail->paymentPending($fresh),
                'successful' => $mail->paymentSuccessful($fresh),
                'successful_excess' => $mail->paymentExcessAlert($fresh),
                'failed', 'invalid' => $mail->paymentFailed($fresh),
                default => null,
            };
        });
    }
}
