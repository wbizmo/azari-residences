<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TravelVoucher extends Model
{
    protected $guarded = [];
    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'redeemed_at' => 'datetime'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(TravelFulfillment::class, 'travel_fulfillment_id');
    }
}
