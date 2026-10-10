<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => fake()->company().' Building',
            'code' => 'BLD'.strtoupper(\Illuminate\Support\Str::random(12)),
            'description' => fake()->sentence(),
            'floors' => fake()->numberBetween(1,15),
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
