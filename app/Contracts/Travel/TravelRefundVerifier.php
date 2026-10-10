<?php

namespace App\Contracts\Travel;

/** Independent proof of a completed provider refund, never an instruction to pay. */
interface TravelRefundVerifier
{
    /**
     * @return array{verified:bool,reference:string,original_capture:string,currency:string,amount_minor:int}
     */
    public function verifyRefund(string $reference): array;
}
