<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        $descriptor = fake()->randomElement([
            'Studio',
            'Deluxe',
            'Executive',
            'Suite',
            'Penthouse',
        ]);

        $name = $descriptor.' '.fake()->unique()->numberBetween(100000, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'apartment',
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
