<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $perPage = 10;

    protected $fillable = [
        'property_id', 'name', 'rule_type', 'starts_on', 'ends_on',
        'days_of_week', 'amount', 'percentage', 'minimum_stay',
        'maximum_stay', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
            'amount' => 'decimal:2',
            'percentage' => 'decimal:3',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
