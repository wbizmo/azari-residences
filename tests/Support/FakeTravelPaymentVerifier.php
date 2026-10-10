<?php

namespace Tests\Support;

use App\Contracts\Travel\TravelPaymentVerifier;

final class FakeTravelPaymentVerifier implements TravelPaymentVerifier
{
    public function verifyCapture(string $reference): array
    {
        return [
            'verified' => str_starts_with($reference, 'sandbox-paid-'),
            'reference' => $reference,
            'currency' => config('travel.test_verified_currency', 'USD'),
            'amount_minor' => (int) config('travel.test_verified_minor', 1200),
        ];
    }
}
