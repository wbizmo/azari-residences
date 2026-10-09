<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyVerifiedClaim extends Model
{
    public const TYPES = [
        'address' => 'Address verified',
        'wifi_speed' => 'Wi-Fi speed verified',
        'power_backup' => 'Backup power verified',
        'security' => 'Security measures verified',
        'water_reliability' => 'Water reliability verified',
        'accessibility' => 'Accessibility features verified',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
