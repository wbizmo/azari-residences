<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestIdentityDocument extends Model
{
    protected $fillable = ['booking_guest_id', 'document_type', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256'];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(BookingGuest::class, 'booking_guest_id');
    }
}
