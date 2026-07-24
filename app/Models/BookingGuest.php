<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingGuest extends Model
{
    protected $fillable = ['booking_id', 'type', 'position', 'first_name', 'last_name', 'is_lead'];

    protected $casts = ['is_lead' => 'boolean'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function identityDocument(): HasOne
    {
        return $this->hasOne(GuestIdentityDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
