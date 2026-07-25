<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserIdentityDocument extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'replaced_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function identityType(): BelongsTo { return $this->belongsTo(IdentityType::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function replaces(): BelongsTo { return $this->belongsTo(self::class, 'replaces_id'); }
    public function bookingLinks(): HasMany { return $this->hasMany(BookingIdentityLink::class); }
}
