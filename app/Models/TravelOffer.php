<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelOffer extends Model
{
    protected $fillable = [
        'travel_supplier_id', 'kind', 'title', 'origin', 'destination', 'timezone',
        'max_party', 'currency', 'base_minor', 'tax_minor', 'fee_minor', 'deposit_minor',
        'terms', 'eligibility', 'starts_at', 'expires_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'terms' => 'array', 'eligibility' => 'array',
            'starts_at' => 'datetime', 'expires_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(TravelSupplier::class, 'travel_supplier_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(TravelExperienceSlot::class)->orderBy('starts_at');
    }

    public function isRequestable(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now())
            && $this->expires_at->gt(now()) && $this->supplier->isApproved()
            && $this->kind === $this->supplier->kind
            && in_array($this->currency, ['USD', 'EUR', 'GBP', 'NGN', 'CAD'], true)
            && ($this->kind !== 'flight'
                || app(\App\Services\Travel\TravelPartnerGateway::class)->supports($this->supplier));
    }

    public function totalMinor(int $partySize): int
    {
        // Deposits are displayed separately, not silently charged as rental revenue.
        return ($this->base_minor + $this->tax_minor + $this->fee_minor) * $partySize;
    }
}
