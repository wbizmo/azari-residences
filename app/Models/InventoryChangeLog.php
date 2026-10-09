<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryChangeLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'changes' => 'array',
            'before_snapshot' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
