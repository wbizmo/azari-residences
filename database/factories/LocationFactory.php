<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $city = fake()->city();

        return [
            'name' => $city,
            'slug' => Str::slug($city),
            'country' => 'Nigeria',
            'city' => $city,
            'address' => fake()->streetAddress(),
            'timezone' => 'Africa/Lagos',
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
