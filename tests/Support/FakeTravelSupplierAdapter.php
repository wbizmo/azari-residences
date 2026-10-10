<?php

namespace Tests\Support;

use App\Contracts\Travel\TravelSupplierAdapter;
use App\Models\TravelOffer;
use App\Models\TravelRequest;

final class FakeTravelSupplierAdapter implements TravelSupplierAdapter
{
    public function reprice(TravelOffer $offer, array $criteria): array
    {
        return [
            'available' => true,
            'currency' => $offer->currency,
            'total_minor' => $offer->totalMinor($criteria['party_size']),
            'provider_offer_id' => 'sandbox-quote-'.$offer->id,
            'expires_at' => now()->addMinutes(4)->toIso8601String(),
        ];
    }

    public function reserve(TravelRequest $request, string $providerIdempotencyKey): array
    {
        throw new \LogicException('Test adapter cannot issue real supplier reservations.');
    }

    public function cancel(TravelRequest $request, string $providerReference): array
    {
        throw new \LogicException('Test adapter cannot cancel real supplier reservations.');
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $provided = $headers['x-resavar-supplier-signature'][0] ?? '';
        $expected = hash_hmac('sha256', $rawBody, 'travel-test-only-secret');
        return is_string($provided) && hash_equals($expected, $provided);
    }
}
