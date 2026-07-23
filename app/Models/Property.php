<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Property extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'location',
        'country',
        'property_type',
        'bedrooms',
        'bathrooms',
        'max_guests',
        'nightly_rate',
        'currency',
        'short_description',
        'description',
        'cover_image',
        'gallery',
        'is_featured',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'nightly_rate' => 'decimal:2',
        ];
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (self $property): void {
            if (! $property->slug) {
                $property->slug = Str::slug($property->name).'-'.Str::lower(Str::random(5));
            }
        });
    }
}
