<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerLedgerEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'owner_share_percentage' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
