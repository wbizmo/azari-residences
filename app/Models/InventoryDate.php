<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDate extends Model
{
    protected $fillable = [
        'accommodation_type_id', 'date', 'sellable_inventory', 'maintenance_inventory',
        'stop_sell', 'closed_to_arrival', 'closed_to_departure', 'minimum_stay',
        'maximum_stay', 'price_override',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'stop_sell' => 'boolean',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
            'price_override' => 'decimal:2',
        ];
    }

    public function accommodationType(): BelongsTo
    {
        return $this->belongsTo(AccommodationType::class);
    }
}
