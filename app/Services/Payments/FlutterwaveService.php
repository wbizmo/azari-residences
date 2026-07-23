<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use LogicException;

final class FlutterwaveService implements PaymentProvider
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function enabled(): bool
    {
        return (bool) config('azari.payments.flutterwave.enabled');
    }

    public function initialise(array $payload): array
    {
        throw new LogicException('Flutterwave integration is scheduled for Sprint 08.');
    }

    public function verify(string $reference): array
    {
        throw new LogicException('Flutterwave integration is scheduled for Sprint 08.');
    }

    public function refund(string $reference, int|float $amount): array
    {
        throw new LogicException('Flutterwave integration is scheduled for Sprint 08.');
    }
}
