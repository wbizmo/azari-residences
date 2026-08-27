<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingGuestVerificationInvite extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'code_sent_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'invite_sent_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function bookingGuest(): BelongsTo
    {
        return $this->belongsTo(BookingGuest::class);
    }
}
