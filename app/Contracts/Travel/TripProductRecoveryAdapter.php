<?php

namespace App\Contracts\Travel;

/**
 * Contracted product only. Never initiates payment and never promises that an
 * accommodation stay can be canceled with a separate supplier operation.
 */
interface TripProductRecoveryAdapter
{
    /** Query actual supplier source of truth by stable product + request ID. */
    public function reconcile(string $itemId,string $idempotencyKey): array;

    /** Request supplier-specific cancellation with the SAME durable key. */
    public function requestCancellation(string $itemId,string $idempotencyKey): array;
}
