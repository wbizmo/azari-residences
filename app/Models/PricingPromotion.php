<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPromotion extends Model
{
    protected $fillable = [
        'property_id', 'accommodation_type_id', 'rate_plan_id', 'name',
        'discount_type', 'discount_value', 'maximum_discount', 'stay_starts_on',
        'stay_ends_on', 'book_starts_at', 'book_ends_at', 'minimum_nights',
        'maximum_nights', 'is_stackable', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:4',
            'maximum_discount' => 'decimal:2',
            'stay_starts_on' => 'date',
            'stay_ends_on' => 'date',
            'book_starts_at' => 'datetime',
            'book_ends_at' => 'datetime',
            'is_stackable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(RatePlan::class); }

    public function scopeBookableNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('book_starts_at')->orWhere('book_starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('book_ends_at')->orWhere('book_ends_at', '>=', now()));
    }
}
