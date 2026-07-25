<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLoginHistory extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return ['logged_in_at' => 'datetime', 'logged_out_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
