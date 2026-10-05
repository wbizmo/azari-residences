<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPolicy extends Model
{
    protected $fillable = [
        'property_id', 'name', 'payment_type', 'deposit_type', 'deposit_value',
        'balance_due_days_before_arrival', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'deposit_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function ratePlans(): HasMany { return $this->hasMany(RatePlan::class); }
}
