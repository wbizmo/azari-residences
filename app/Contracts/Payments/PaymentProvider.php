<?php

namespace App\Contracts\Payments;

interface PaymentProvider
{
    public function name(): string;
    public function enabled(): bool;
    public function mode(): string;
    public function initialise(array $payload): array;
    public function verify(string $providerReference): array;
    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool;
    public function webhookReferences(array $payload): array;
    public function healthCheck(): array;
}
