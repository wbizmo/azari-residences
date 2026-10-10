<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class TravelFulfillment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_confirmed_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(TravelVoucher::class);
    }
}
