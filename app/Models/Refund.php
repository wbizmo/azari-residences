<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function processedBy(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }
}
