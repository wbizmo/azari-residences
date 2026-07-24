<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'name',
        'slug',
        'country',
        'city',
        'address',
        'timezone',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            $location->slug = $location->slug ?: Str::slug($location->name);
        });
    }
}