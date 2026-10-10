<?php

namespace App\Services\PhaseThree;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PartnerAccessService
{
    /** Active accounts are issued through a controlled onboarding process; never grant a public default key. */
    public function requireScope(Request $request, string $scope): object
    {
        $bearer = (string) $request->bearerToken();
        abort_unless(strlen($bearer) >= 32 && strlen($bearer) <= 200, 401);
        $partner = DB::table('partner_clients')
            ->where('key_hash', hash('sha256', $bearer))
            ->where('is_active', true)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
        abort_unless($partner !== null, 401);
        $scopes = json_decode($partner->scopes, true);
        abort_unless(is_array($scopes) && in_array($scope, $scopes, true), 403);
        return $partner;
    }
}
