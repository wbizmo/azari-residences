<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerificationAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reported_amount' => 'decimal:2',
            'safe_response' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
