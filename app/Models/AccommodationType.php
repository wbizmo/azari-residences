<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationType extends Model
{
    protected $fillable = [
        'property_id', 'room_type_id', 'name', 'slug', 'code', 'description',
        'bedrooms', 'bathrooms', 'adult_capacity', 'child_capacity', 'max_guests',
        'bed_configuration', 'room_size', 'total_inventory', 'base_rate',
        'weekend_rate', 'cleaning_fee', 'service_charge', 'security_deposit',
        'tax_rate', 'currency', 'minimum_stay', 'maximum_stay', 'same_day_booking',
        'cover_image', 'gallery', 'is_active', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'room_size' => 'decimal:2',
            'base_rate' => 'decimal:2',
            'weekend_rate' => 'decimal:2',
            'cleaning_fee' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'same_day_booking' => 'boolean',
            'is_active' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function roomType(): BelongsTo { return $this->belongsTo(RoomType::class); }
    public function amenities(): BelongsToMany { return $this->belongsToMany(Amenity::class, 'accommodation_type_amenity'); }
    public function inventoryDates(): HasMany { return $this->hasMany(InventoryDate::class); }
    public function ratePlans(): HasMany { return $this->hasMany(RatePlan::class)->orderBy('sort_order')->orderBy('id'); }
    public function dailyRates(): HasMany { return $this->hasMany(DailyRate::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function bookingHolds(): HasMany { return $this->hasMany(BookingHold::class); }

    public function capacityPerUnit(): int
    {
        return max(1, (int) ($this->max_guests ?: ($this->adult_capacity + $this->child_capacity)));
    }
}
