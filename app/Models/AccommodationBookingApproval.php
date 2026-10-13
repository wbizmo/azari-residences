<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccommodationBookingApproval extends Model
{
    protected $fillable = [
        'booking_id', 'status', 'reviewed_by', 'reviewed_at',
        'review_note', 'proposed_check_in', 'proposed_check_out',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'proposed_check_in' => 'date',
            'proposed_check_out' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
