<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuestIdentityDocument extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function guest(): BelongsTo { return $this->belongsTo(BookingGuest::class, 'booking_guest_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function bookingLink(): HasOne { return $this->hasOne(BookingIdentityLink::class); }
}
