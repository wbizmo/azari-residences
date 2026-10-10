<?php

namespace Tests\Support;

use App\Contracts\Travel\TravelRefundVerifier;

final class FakeTravelRefundVerifier implements TravelRefundVerifier
{
    public function verifyRefund(string $reference): array
    {
        return [
            'verified' => str_starts_with($reference, 'sandbox-refund-'),
            'reference' => $reference,
            'original_capture' => config('travel.test_original_capture', 'sandbox-paid-0001'),
            'currency' => config('travel.test_refund_currency', 'USD'),
            'amount_minor' => (int) config('travel.test_refund_minor', 600),
        ];
    }
}
