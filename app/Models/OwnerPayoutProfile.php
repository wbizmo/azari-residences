<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerPayoutProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
