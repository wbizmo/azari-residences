<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingModificationRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_changes' => 'array',
            'price_quote' => 'array',
            'quote_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
