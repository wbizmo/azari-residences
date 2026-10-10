<?php

namespace App\Contracts\Travel;

use App\Models\DiningRequest;

/**
 * Provider-specific licensed dining reservation verification. Contract owner
 * implements actual signed provider API lookup (never trusts client input).
 */
interface DiningReservationVerifier
{
    /**
     * @return array{verified:bool,state:string,reference:string,dining_request_id:string,
     *   partner_id:int,party_size:int,requested_for:string}
     */
    public function verify(DiningRequest $request, string $providerReference): array;
}
