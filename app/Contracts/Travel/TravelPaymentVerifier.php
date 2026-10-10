<?php

namespace App\Contracts\Travel;

/**
 * Only an authorized payment adapter may attest that money was actually
 * captured for a travel-only order. Never accept a client-supplied "paid" flag.
 */
interface TravelPaymentVerifier
{
    /**
     * @return array{verified:bool,reference:string,currency:string,amount_minor:int}
     */
    public function verifyCapture(string $reference): array;
}
