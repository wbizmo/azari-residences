<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerPayoutProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function destinationLabel(): string
    {
        return $this->preferred_gateway === 'paypal'
            ? (string) $this->paypal_recipient
            : (string) $this->stripe_connected_account_id;
    }
}
