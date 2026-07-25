<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingIdentityLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_booking_owner' => 'boolean'];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function bookingGuest(): BelongsTo { return $this->belongsTo(BookingGuest::class); }
    public function userIdentityDocument(): BelongsTo { return $this->belongsTo(UserIdentityDocument::class); }
    public function guestIdentityDocument(): BelongsTo { return $this->belongsTo(GuestIdentityDocument::class); }
    public function linkedBy(): BelongsTo { return $this->belongsTo(User::class, 'linked_by'); }
}
