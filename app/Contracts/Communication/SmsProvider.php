<?php

namespace App\Contracts\Communication;

interface SmsProvider
{
    public function enabled(): bool;

    public function send(string $recipient, string $message, array $options = []): array;
}
