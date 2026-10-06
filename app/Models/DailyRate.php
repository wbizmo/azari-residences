<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRate extends Model
{
    protected $fillable = [
        'accommodation_type_id', 'rate_plan_id', 'date', 'amount',
        'minimum_stay', 'maximum_stay', 'stop_sell',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'stop_sell' => 'boolean',
        ];
    }

    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }
}
