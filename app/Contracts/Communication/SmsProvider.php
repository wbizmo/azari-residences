<?php

namespace App\Contracts\Communication;

interface SmsProvider
{
    public function enabled(): bool;

    public function whatsappEnabled(): bool;

    public function send(string $recipient, string $message, array $options = []): array;

    public function sendWhatsApp(string $recipient, string $message, array $options = []): array;
}
