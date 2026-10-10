<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingMessage extends Model
{
    protected $guarded = [];
    protected $casts = [
        'read_at' => 'datetime',
        'attachment_scanned_at' => 'datetime',
        'attachment_scan_claimed_at' => 'datetime',
        'attachment_scan_next_attempt_at' => 'datetime',
    ];
}
