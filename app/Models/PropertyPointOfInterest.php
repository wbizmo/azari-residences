<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPointOfInterest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['distance_km' => 'decimal:2'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
