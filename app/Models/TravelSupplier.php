<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelSupplier extends Model
{
    protected $fillable = [
        'kind', 'name', 'status', 'integration_key', 'support_email',
        'terms_url', 'approved_regions', 'compliance_evidence',
        'contract_verified_at', 'safety_verified_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_regions' => 'array',
            'compliance_evidence' => 'encrypted:array',
            'contract_verified_at' => 'datetime',
            'safety_verified_at' => 'datetime',
        ];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TravelOffer::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved'
            && $this->contract_verified_at !== null
            && $this->safety_verified_at !== null
            && $this->approved_by !== null;
    }
}
