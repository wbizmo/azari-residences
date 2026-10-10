<?php

namespace Tests\Support;

use App\Contracts\Travel\DiningReservationVerifier;
use App\Models\DiningRequest;

final class FakeDiningReservationVerifier implements DiningReservationVerifier
{
    public function verify(DiningRequest $request,string $providerReference): array
    {
        return [
            'verified' => ! config('travel.dining_test_reject',false),
            'state' => config('travel.dining_test_state','confirmed'),
            'reference' => $providerReference,
            'dining_request_id' => $request->id,
            'partner_id' => $request->dining_partner_id,
            'party_size' => $request->party_size,
            'requested_for' => $request->requested_for->toIso8601String(),
        ];
    }
}
