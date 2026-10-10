<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
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
        'timezone',
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
        'accessibility_notes',
        'children_policy',
        'pet_policy',
        'smoking_policy',
        'party_policy',
        'check_in_instructions',
        'check_out_instructions',
        'host_name',
        'host_description',
        'house_rules',
        'faqs',
        'is_featured',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'house_rules' => 'array',
            'faqs' => 'array',
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

    public function photoModerations(): HasMany
    {
        return $this->hasMany(PropertyPhotoModeration::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class)->orderByDesc('priority');
    }

    public function accommodationTypes(): HasMany
    {
        return $this->hasMany(AccommodationType::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function publicAccommodationTypes(): HasMany
    {
        return $this->accommodationTypes()
            ->where('is_active', true)
            ->where('is_published', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)
            ->where('verified_stay', true)
            ->where('status', 'approved');
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(UserFavourite::class);
    }

    public function verifiedClaims(): HasMany
    {
        return $this->hasMany(PropertyVerifiedClaim::class);
    }

    public function publicVerifiedClaims(): HasMany
    {
        return $this->verifiedClaims()->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now());
    }

    public function recentViews(): HasMany
    {
        return $this->hasMany(RecentlyViewedProperty::class);
    }

    public function inventoryChangeLogs(): HasMany
    {
        return $this->hasMany(InventoryChangeLog::class);
    }

    public function maintenancePeriods(): HasMany
    {
        return $this->hasMany(MaintenancePeriod::class);
    }

    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PropertyPointOfInterest::class)
            ->orderBy('sort_order')
            ->orderBy('id');
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
            $property->currency = strtoupper((string) ($property->currency ?: config('localization.default_currency', config('azari.currency', 'USD'))));
            $property->timezone = $property->timezone ?: $property->locationRecord?->timezone ?: config('localization.platform_timezone', config('azari.timezone', 'UTC'));

            if ($property->ownership_type === 'third_party' && blank($property->owner_share_percentage)) {
                $property->owner_share_percentage = (float) config('azari.owners.default_owner_share_percentage', 88);
            }

            $property->slug = $property->slug
                ?: Str::slug($property->name).'-'.Str::lower(Str::random(5));

            $property->code = $property->code
                ?: 'AZR-'.Str::upper(Str::random(8));
        });

        static::created(function (self $property): void {
            if (! Schema::hasTable('accommodation_types') || ! Schema::hasTable('rate_plans')) {
                return;
            }

            $type = $property->accommodationTypes()->create([
                'room_type_id' => $property->room_type_id,
                'name' => $property->property_type
                    ? Str::headline((string) $property->property_type)
                    : 'Standard accommodation',
                'slug' => 'standard',
                'code' => 'RES-'.$property->getKey().'-STD',
                'description' => $property->short_description,
                'bedrooms' => max(0, (int) ($property->bedrooms ?? 1)),
                'bathrooms' => max(0, (int) ($property->bathrooms ?? 1)),
                'adult_capacity' => max(1, (int) ($property->adult_capacity ?? $property->max_guests ?? 2)),
                'child_capacity' => max(0, (int) ($property->child_capacity ?? 0)),
                'max_guests' => max(1, (int) ($property->max_guests ?? 2)),
                'bed_configuration' => $property->bed_configuration,
                'room_size' => $property->room_size,
                'total_inventory' => 1,
                'base_rate' => (float) ($property->nightly_rate ?? 0),
                'weekend_rate' => $property->weekend_rate,
                'cleaning_fee' => (float) ($property->cleaning_fee ?? 0),
                'service_charge' => (float) ($property->service_charge ?? $property->service_fee ?? 0),
                'security_deposit' => (float) ($property->security_deposit ?? 0),
                'tax_rate' => (float) ($property->tax_rate ?? 0),
                'currency' => (string) ($property->currency ?: config('azari.currency', 'USD')),
                'minimum_stay' => max(1, (int) ($property->minimum_stay ?? 1)),
                'maximum_stay' => $property->maximum_stay,
                'same_day_booking' => (bool) ($property->same_day_booking ?? false),
                'cover_image' => $property->cover_image,
                'gallery' => $property->gallery,
                'is_active' => ! in_array((string) $property->status, ['inactive', 'archived'], true),
                'is_published' => (bool) $property->is_published,
                'sort_order' => 0,
            ]);

            $type->ratePlans()->create([
                'name' => 'Standard',
                'code' => 'STANDARD',
                'pricing_adjustment_type' => 'none',
                'pricing_adjustment' => 0,
                'is_refundable' => true,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ]);
        });

        // An address proof must not survive a changed property address or
        // coordinate. Other claim types remain independently evaluated.
        static::updated(function (self $property): void {
            if ($property->wasChanged([
                'formatted_address', 'address_line_1', 'address_city',
                'address_region', 'address_country_code', 'latitude', 'longitude',
            ]) && Schema::hasTable('property_verified_claims')) {
                PropertyVerifiedClaim::query()
                    ->where('property_id', $property->getKey())
                    ->where('claim_type', 'address')
                    ->where('status', 'verified')
                    ->update(['status' => 'revoked', 'updated_at' => now()]);
            }
        });

        static::saving(function (self $property): void {
            $property->currency = strtoupper((string) ($property->currency ?: config('localization.default_currency', config('azari.currency', 'USD'))));
            $property->timezone = $property->timezone ?: $property->locationRecord?->timezone ?: config('localization.platform_timezone', config('azari.timezone', 'UTC'));

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
