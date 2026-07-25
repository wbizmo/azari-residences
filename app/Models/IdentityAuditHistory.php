<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityAuditHistory extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array { return ['metadata' => 'array']; }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
