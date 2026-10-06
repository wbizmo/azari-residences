<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStatusHistory extends Model
{
    protected $perPage = 10;

    protected $fillable = [
        'booking_id', 'changed_by', 'from_status', 'to_status', 'note', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
