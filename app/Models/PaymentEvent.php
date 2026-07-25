<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'processed' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'safe_payload' => 'array',
        ];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
