<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }
}
