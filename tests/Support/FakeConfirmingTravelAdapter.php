<?php

namespace Tests\Support;

use App\Contracts\Travel\TravelSupplierAdapter;
use App\Models\TravelOffer;
use App\Models\TravelRequest;

final class FakeConfirmingTravelAdapter implements TravelSupplierAdapter
{
    public function reprice(TravelOffer $offer, array $criteria): array
    {
        return [
            'available' => true,
            'currency' => $offer->currency,
            'total_minor' => $offer->totalMinor($criteria['party_size']),
            'provider_offer_id' => 'sandbox-'.$offer->id,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ];
    }

    public function reserve(TravelRequest $request, string $providerIdempotencyKey): array
    {
        return [
            'confirmed' => true,
            'reference' => 'sandbox-'.hash('sha256', $providerIdempotencyKey),
            'currency' => $request->currency,
            'amount_minor' => (int) $request->quoted_total_minor,
            'pnr' => 'AB12345',
            'ticket_numbers' => array_fill(0, (int) $request->party_size, 'TEST-ISSUE-ONLY'),
        ];
    }

    public function cancel(TravelRequest $request, string $providerReference): array
    {
        throw new \LogicException('Test cancellation is not a contracted supplier cancel.');
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return ($headers['x-test-signed'][0] ?? null) === 'local-test-only';
    }
}
