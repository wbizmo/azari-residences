<?php

namespace App\Contracts\Payments;

interface PaymentProvider
{
    public function name(): string;

    public function enabled(): bool;

    public function initialise(array $payload): array;

    public function verify(string $reference): array;

    public function refund(string $reference, int|float $amount): array;
}
