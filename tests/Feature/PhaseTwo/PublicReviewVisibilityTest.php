<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReviewVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_guest_reviews_never_reach_property_page(): void
    {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
        ]);
        $guest = User::factory()->create();
        foreach ([
            ['status' => 'approved', 'verified_stay' => true, 'body' => 'PUBLIC_VALID_REVIEW approved and safe.'],
            ['status' => 'pending', 'verified_stay' => true, 'body' => 'PRIVATE_PENDING_REVIEW must remain hidden.'],
            ['status' => 'hidden', 'verified_stay' => true, 'body' => 'PRIVATE_HIDDEN_REVIEW must remain hidden.'],
            ['status' => 'approved', 'verified_stay' => false, 'body' => 'PRIVATE_UNVERIFIED_REVIEW must remain hidden.'],
        ] as $row) {
            $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
            Review::query()->create([
                ...$row,
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'user_id' => $guest->id,
                'rating' => 5,
            ]);
        }

        $response = $this->get(route('properties.show', $property))->assertOk();
        $response->assertSeeText('PUBLIC_VALID_REVIEW');
        $response->assertDontSeeText('PRIVATE_PENDING_REVIEW');
        $response->assertDontSeeText('PRIVATE_HIDDEN_REVIEW');
        $response->assertDontSeeText('PRIVATE_UNVERIFIED_REVIEW');
    }
}
