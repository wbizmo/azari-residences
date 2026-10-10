<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelExperienceSlot extends Model
{
    protected $fillable = ['travel_offer_id', 'starts_at', 'capacity'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(TravelOffer::class, 'travel_offer_id');
    }
}
