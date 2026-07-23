<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Property extends Model
{
    protected $fillable = [
        'location_id', 'building_id', 'room_type_id', 'name', 'slug',
        'code', 'unit_number', 'floor', 'location', 'country',
        'property_type', 'bedrooms', 'bathrooms', 'max_guests',
        'adult_capacity', 'child_capacity', 'bed_configuration',
        'room_size', 'check_in_time', 'check_out_time', 'nightly_rate',
        'weekend_rate', 'cleaning_fee', 'security_deposit',
        'service_charge', 'tax_rate', 'currency', 'short_description',
        'description', 'cover_image', 'gallery', 'video_url',
        'virtual_tour_url', 'status', 'internal_notes', 'is_featured',
        'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'nightly_rate' => 'decimal:2',
            'weekend_rate' => 'decimal:2',
            'cleaning_fee' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'tax_rate' => 'decimal:3',
        ];
    }

    public function locationRecord(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class)->orderByDesc('priority');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (self $property): void {
            $property->slug = $property->slug
                ?: Str::slug($property->name).'-'.Str::lower(Str::random(5));

            $property->code = $property->code
                ?: 'AZR-'.Str::upper(Str::random(8));
        });
    }
}
