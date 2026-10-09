<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyVerifiedClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropertyClaimVerificationController extends Controller
{
    public function update(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'claim_type' => ['required', Rule::in(array_keys(PropertyVerifiedClaim::TYPES))],
            'action' => ['required', Rule::in(['verify', 'revoke'])],
            'evidence_reference' => [
                Rule::requiredIf($request->input('action') === 'verify'),
                'nullable', 'string', 'max:255',
            ],
            'expires_at' => [
                Rule::requiredIf($request->input('action') === 'verify'),
                'nullable', 'date', 'after:today',
            ],
        ]);

        DB::transaction(function () use ($request, $property, $data): void {
            // Serialize reviews of a property against one another.
            $locked = Property::query()->whereKey($property->getKey())->lockForUpdate()->firstOrFail();
            $claim = PropertyVerifiedClaim::query()->firstOrNew([
                'property_id' => $locked->id,
                'claim_type' => $data['claim_type'],
            ]);

            $previous = $claim->exists
                ? $claim->only(['status', 'verified_at', 'expires_at', 'verified_by', 'evidence_reference'])
                : [];

            if ($data['action'] === 'verify') {
                $claim->fill([
                    'status' => 'verified',
                    'evidence_reference' => $data['evidence_reference'],
                    'expires_at' => $data['expires_at'],
                    'verified_at' => now(),
                    'verified_by' => $request->user()->id,
                ]);
            } else {
                abort_unless($claim->exists, 422, 'This property claim has not been verified.');
                $claim->status = 'revoked';
            }

            $claim->save();
            AuditLog::record('property.claim_reviewed', $locked, $previous, [
                'claim_type' => $claim->claim_type,
                'claim_id' => $claim->id,
                'status' => $claim->status,
                'verified_by' => $claim->verified_by,
                'expires_at' => $claim->expires_at?->toIso8601String(),
            ], ['reviewer_id' => $request->user()->id]);
        }, 3);

        return back()->with('status', 'Property claim review recorded.');
    }
}
