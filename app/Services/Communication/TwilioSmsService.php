<?php

namespace App\Services\Communication;

use App\Contracts\Communication\SmsProvider;
use LogicException;

final class TwilioSmsService implements SmsProvider
{
    public function enabled(): bool
    {
        return (bool) config('azari.twilio.enabled');
    }

    public function send(string $recipient, string $message, array $options = []): array
    {
        throw new LogicException('Twilio integration is scheduled for Sprint 10.');
    }
}
