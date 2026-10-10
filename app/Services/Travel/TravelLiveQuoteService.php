<?php

namespace App\Services\Travel;

use App\Models\TravelOffer;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class TravelLiveQuoteService
{
    /**
     * Reprice without PII. Especially for flight fares, accept no cached
     * offer as a chargeable or reservable supplier confirmation.
     */
    public function verify(TravelOffer $offer, array $criteria): array
    {
        $offer->loadMissing('supplier');
        $raw = app(TravelPartnerGateway::class)->resolve($offer->supplier)
            ->reprice($offer, [
                'party_size' => (int) $criteria['party_size'],
                'slot_id' => $criteria['slot_id'] ?? null,
            ]);

        if (! is_array($raw)
            || ($raw['available'] ?? null) !== true
            || ($raw['currency'] ?? null) !== $offer->currency
            || ! is_int($raw['total_minor'] ?? null)
            || ! is_string($raw['provider_offer_id'] ?? null)
            || ! preg_match('/^[A-Za-z0-9_.:-]{5,160}$/D', $raw['provider_offer_id'])
            || ! is_string($raw['expires_at'] ?? null)) {
            throw ValidationException::withMessages([
                'offer_id' => 'The supplier cannot verify a current price and available capacity.',
            ]);
        }

        try {
            $expiresAt = Carbon::parse($raw['expires_at']);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'offer_id' => 'The supplier quote has an invalid expiry.',
            ]);
        }

        if ($expiresAt->lte(now()->addSeconds(30))
            || $expiresAt->gt(now()->addMinutes(30))
            || $raw['total_minor'] < 0
            || $raw['total_minor'] !== $offer->totalMinor((int) $criteria['party_size'])) {
            throw ValidationException::withMessages([
                'offer_id' => 'The supplier price changed or the rate expired. Refresh your offer.',
            ]);
        }

        return [
            'provider_offer_id' => $raw['provider_offer_id'],
            'expires_at' => $expiresAt,
            'total_minor' => $raw['total_minor'],
        ];
    }
}
