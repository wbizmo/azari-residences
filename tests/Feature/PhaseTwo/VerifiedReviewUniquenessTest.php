<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedReviewUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_prevents_duplicate_reviews_for_one_booking(): void
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        Review::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'user_id' => $guest->id,
            'rating' => 4,
            'status' => 'pending',
            'verified_stay' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Review::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'user_id' => $guest->id,
            'rating' => 5,
            'status' => 'pending',
            'verified_stay' => true,
        ]);
    }
}
