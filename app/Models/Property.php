<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'owner_id',
        'ownership_type',
        'owner_listing_id',
        'owner_share_percentage',
        'managed_for_owner',
        'location_id',
        'building_id',
        'room_type_id',
        'name',
        'slug',
        'code',
        'unit_number',
        'floor',
        'location',
        'country',
        'formatted_address',
        'address_line_1',
        'address_line_2',
        'address_city',
        'address_region',
        'address_postal_code',
        'address_country_code',
        'latitude',
        'longitude',
        'property_type',
        'bedrooms',
        'bathrooms',
        'max_guests',
        'adult_capacity',
        'child_capacity',
        'bed_configuration',
        'room_size',
        'check_in_time',
        'check_out_time',
        'nightly_rate',
        'weekend_rate',
        'cleaning_fee',
        'security_deposit',
        'service_charge',
        'tax_rate',
        'currency',
        'short_description',
        'description',
        'cover_image',
        'gallery',
        'video_url',
        'virtual_tour_url',
        'minimum_stay',
        'maximum_stay',
        'same_day_booking',
        'status',
        'internal_notes',
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
            'weekend_rate' => 'decimal:2',
            'cleaning_fee' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'same_day_booking' => 'boolean',
            'managed_for_owner' => 'boolean',
            'ownership_type' => 'string',
            'owner_share_percentage' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function ownerListing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'owner_listing_id');
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
            $property->ownership_type = $property->owner_id ? 'third_party' : ($property->ownership_type ?: 'azari');
            $property->managed_for_owner = $property->ownership_type === 'third_party';
            $property->currency = (string) config('azari.currency', 'USD');

            if ($property->ownership_type === 'third_party' && blank($property->owner_share_percentage)) {
                $property->owner_share_percentage = (float) config('azari.owners.default_owner_share_percentage', 88);
            }

            $property->slug = $property->slug
                ?: Str::slug($property->name).'-'.Str::lower(Str::random(5));

            $property->code = $property->code
                ?: 'AZR-'.Str::upper(Str::random(8));
        });

        static::saving(function (self $property): void {
            $property->currency = (string) config('azari.currency', 'USD');

            if ($property->isDirty('owner_id') && ! $property->isDirty('ownership_type')) {
                $property->ownership_type = $property->owner_id ? 'third_party' : 'azari';
            }

            if ($property->ownership_type === 'azari') {
                $property->owner_id = null;
                $property->owner_listing_id = null;
                $property->managed_for_owner = false;
                $property->owner_share_percentage = 100;
            } else {
                $property->managed_for_owner = true;
                $property->owner_share_percentage = $property->owner_share_percentage
                    ?: (float) config('azari.owners.default_owner_share_percentage', 88);
            }
        });
    }

    public function isAzariOwned(): bool
    {
        return $this->ownership_type === 'azari' || $this->owner_id === null;
    }
}
