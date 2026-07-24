<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $name = fake()->streetName().' Residence';

        return [
            'location_id' => Location::factory(),
            'building_id' => Building::factory(),
            'room_type_id' => RoomType::factory(),

            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000,9999),

            'location' => fake()->city(),
            'country' => 'Nigeria',

            'property_type' => 'Apartment',

            'bedrooms' => fake()->numberBetween(1,4),
            'bathrooms' => fake()->numberBetween(1,3),
            'max_guests' => fake()->numberBetween(2,8),

            'nightly_rate' => fake()->numberBetween(30000,120000),

            'currency' => 'NGN',

            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),

            'cover_image' => null,
            'gallery' => [],

            'is_featured' => false,
            'is_published' => true,
            'sort_order' => 1,
        ];
    }
}
