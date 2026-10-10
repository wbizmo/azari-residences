<?php

namespace App\Contracts\Travel;

use App\Models\TravelOffer;
use App\Models\TravelRequest;

/**
 * A provider-specific implementation requires commercial authorization and
 * successful sandbox certification. Implementations must not charge directly:
 * they return verified provider facts for the independent payment orchestrator.
 */
interface TravelSupplierAdapter
{
    /** Real-time rate and capacity with provider offer ID and expiry. */
    public function reprice(TravelOffer $offer, array $criteria): array;

    /** Idempotent supplier dispatch / reservation only after payment approval. */
    public function reserve(TravelRequest $request, string $providerIdempotencyKey): array;

    /** Idempotent supplier cancellation; cannot mutate an accommodation stay. */
    public function cancel(TravelRequest $request, string $providerReference): array;

    /** Authenticate the raw signed callback before processing. */
    public function verifyWebhook(string $rawBody, array $headers): bool;
}
