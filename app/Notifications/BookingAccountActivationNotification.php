<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingAccountActivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $activationUrl,
        private readonly string $bookingReference,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(
                'Your Azari account is ready'
            )
            ->greeting(
                'Welcome to Azari'
            )
            ->line(
                'Your booking '.$this->bookingReference.' has been confirmed following verified payment.'
            )
            ->line(
                'We created a secure Azari customer account for you so you can manage your stay, view booking history, access receipts and confirmations, and use guest services.'
            )
            ->line(
                'No password has been generated or sent by email. Use the secure link below to choose your own password.'
            )
            ->action(
                'Activate account & set password',
                $this->activationUrl
            )
            ->line(
                'Setting your password also verifies ownership of this email address.'
            )
            ->line(
                'If the activation link expires, use the Forgot password option with this same email address.'
            );
    }
}
