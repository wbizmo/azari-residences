<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingMessageAlertOutbox extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'datetime',
            'claimed_at' => 'datetime',
            'queued_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(BookingMessage::class, 'booking_message_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
