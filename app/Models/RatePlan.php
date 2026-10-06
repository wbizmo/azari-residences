<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatePlan extends Model
{
    protected $fillable = [
        'accommodation_type_id', 'cancellation_policy_id', 'payment_policy_id',
        'name', 'code', 'pricing_adjustment_type', 'pricing_adjustment', 'meal_plan',
        'inclusions', 'minimum_stay', 'maximum_stay', 'minimum_advance_days',
        'maximum_advance_days', 'is_refundable', 'is_active', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing_adjustment' => 'decimal:4',
            'inclusions' => 'array',
            'is_refundable' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function cancellationPolicy(): BelongsTo { return $this->belongsTo(CancellationPolicy::class); }
    public function paymentPolicy(): BelongsTo { return $this->belongsTo(PaymentPolicy::class); }
    public function dailyRates(): HasMany { return $this->hasMany(DailyRate::class); }

    public function publicLabel(): string
    {
        return $this->name ?: 'Standard';
    }
}
