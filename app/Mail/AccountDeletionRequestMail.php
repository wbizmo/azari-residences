<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reference,
        public string $requesterName,
        public string $requesterEmail,
        public string $requestedAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [$this->requesterEmail],
            subject: 'Account deletion request '.$this->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion-request',
        );
    }
}
