<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CancellationPolicy extends Model
{
    protected $fillable = [
        'property_id', 'name', 'policy_type', 'free_cancel_hours', 'fee_percentage',
        'fee_amount', 'charge_first_night', 'no_show_policy', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_percentage' => 'decimal:4',
            'fee_amount' => 'decimal:2',
            'charge_first_night' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function ratePlans(): HasMany { return $this->hasMany(RatePlan::class); }
}
