<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+2 days', '+1 month');
        $checkOut = (clone $checkIn)->modify('+'.fake()->numberBetween(1,5).' days');

        return [
            'reference' => strtoupper(Str::random(10)),
            'property_id' => Property::factory(),

            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),

            'check_in' => $checkIn,
            'check_out' => $checkOut,

            'adults' => 2,
            'children' => 0,
            'rooms' => 1,

            'status' => 'confirmed',
            'verification_status' => 'verified',

            'currency' => 'NGN',

            'subtotal' => 100000,
            'tax_total' => 7500,
            'total' => 107500,

            'guest_notes' => null,
            'admin_notes' => null,

            'approved_at' => now(),
            'cancelled_at' => null,
        ];
    }
}
