<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPropertyReviewDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function createReview(Property $property, string $body, int $rating,
        string $status = 'approved', bool $verified = true, string $trip = 'family'): Review
    {
        $guest = User::factory()->create();
        $booking = Booking::factory()->create([
            'property_id' => $property->id, 'user_id' => $guest->id,
        ]);
        return Review::query()->create([
            'property_id' => $property->id, 'booking_id' => $booking->id,
            'user_id' => $guest->id, 'rating' => $rating, 'body' => $body,
            'status' => $status, 'verified_stay' => $verified, 'trip_type' => $trip,
        ]);
    }

    public function test_guest_review_directory_only_shows_approved_verified_reviews(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'active']);
        $this->createReview($property, 'PUBLIC_APPROVED_REVIEW with clean information.', 4);
        $this->createReview($property, 'PRIVATE_PENDING_REVIEW must not show.', 1, 'pending');
        $this->createReview($property, 'PRIVATE_HIDDEN_REVIEW must not show.', 1, 'hidden');
        $this->createReview($property, 'PRIVATE_UNVERIFIED_REVIEW must not show.', 1, 'approved', false);

        $r = $this->get(route('properties.reviews', $property))->assertOk();
        $r->assertSeeText('PUBLIC_APPROVED_REVIEW');
        $r->assertDontSeeText('PRIVATE_PENDING_REVIEW');
        $r->assertDontSeeText('PRIVATE_HIDDEN_REVIEW');
        $r->assertDontSeeText('PRIVATE_UNVERIFIED_REVIEW');
        $r->assertSeeText('1 matching review');
    }

    public function test_review_sorting_and_trip_filters_do_not_bypass_verification(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'active']);
        $this->createReview($property, 'FIVE_STAR_APPROVED review for a family visit.', 5, 'approved', true, 'family');
        $this->createReview($property, 'TWO_STAR_APPROVED review for a business trip.', 2, 'approved', true, 'business');

        $this->get(route('properties.reviews', [$property, 'sort' => 'highest']))
            ->assertOk()->assertSeeInOrder(['FIVE_STAR_APPROVED', 'TWO_STAR_APPROVED']);

        $this->get(route('properties.reviews', [$property, 'trip_type' => 'business']))
            ->assertOk()->assertSeeText('TWO_STAR_APPROVED')
            ->assertDontSeeText('FIVE_STAR_APPROVED');

        $this->get(route('properties.reviews', [$property, 'sort' => 'drop-table']))
            ->assertSessionHasErrors('sort');
    }
}
