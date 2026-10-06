<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingOperationalNote extends Model
{
    protected $guarded = [];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
