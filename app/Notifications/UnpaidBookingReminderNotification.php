<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnpaidBookingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $bookingReference,
        private readonly string $paymentUrl,
        private readonly string $expiryLabel,
        private readonly int $minutesRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(
                'Payment reminder for booking '.$this->bookingReference
            )
            ->greeting('Complete your Resavar reservation')
            ->line(
                'Your booking '.$this->bookingReference.' is still awaiting payment.'
            )
            ->line(
                'Approximately '.$this->minutesRemaining
                .' minute(s) remain before the unpaid reservation expires and the dates are released.'
            )
            ->line(
                'Payment deadline: '.$this->expiryLabel.'.'
            )
            ->action(
                'Complete payment',
                $this->paymentUrl
            )
            ->line(
                'If you have already completed payment, no further action is required while Resavar verifies the transaction.'
            );
    }
}
