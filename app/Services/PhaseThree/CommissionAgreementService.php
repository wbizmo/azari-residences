<?php

namespace App\Services\PhaseThree;

use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Contract terms are versioned and future-effective. They do not silently rewrite existing owner ledger rows. */
final class CommissionAgreementService
{
    public function approve(Property $property, User $staff, int $basisPoints, CarbonImmutable $effective): object
    {
        if ($basisPoints < 0 || $basisPoints > 3000 || ! $property->owner_id
            || $effective->lt(CarbonImmutable::today()->addDay())) {
            throw ValidationException::withMessages(['effective_from' => 'An approved agreement must have a future effective date and valid owner.']);
        }
        return DB::transaction(function () use ($property, $staff, $basisPoints, $effective) {
            $locked = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
            $latest = DB::table('commission_agreements')->where('property_id', $locked->id)
                ->orderByDesc('version')->lockForUpdate()->first();
            if ($latest && $latest->effective_from >= $effective->toDateString()) {
                throw ValidationException::withMessages(['effective_from' => 'Agreement dates must increase by version.']);
            }
            $id = DB::table('commission_agreements')->insertGetId([
                'property_id' => $locked->id, 'owner_id' => $locked->owner_id,
                'version' => (int) ($latest->version ?? 0) + 1,
                'commission_basis_points' => $basisPoints,
                'currency' => $locked->currency ?: 'USD',
                'effective_from' => $effective->toDateString(),
                'approved_by' => $staff->id, 'approved_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('commission_agreements')->where('id', $id)->first();
        }, 3);
    }
}
