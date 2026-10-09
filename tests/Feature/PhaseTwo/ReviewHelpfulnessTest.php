<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewHelpfulnessTest extends TestCase
{
    use RefreshDatabase;

    private function publicReview(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'is_published' => true, 'status' => 'active',
        ]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
        $review = Review::query()->create([
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'user_id' => $guest->id,
            'status' => 'approved',
            'verified_stay' => true,
            'rating' => 5,
            'body' => 'A lovely clean property with excellent hospitality.',
        ]);
        return [$owner, $guest, $property, $review];
    }

    public function test_one_voter_cannot_inflate_helpfulness_with_repeat_submissions(): void
    {
        [, , $property, $review] = $this->publicReview();
        $voter = User::factory()->create(['email_verified_at' => now()]);
        $url = route('user.reviews.helpful', $review);

        $this->actingAs($voter)->post($url)->assertRedirect();
        $this->actingAs($voter)->post($url)->assertRedirect();
        $this->assertDatabaseCount('review_helpful_votes', 1);
        $this->assertDatabaseHas('review_helpful_votes', [
            'review_id' => $review->id, 'user_id' => $voter->id,
        ]);

        $this->get(route('properties.reviews', $property))->assertOk()
            ->assertSeeText('1 found this helpful');
    }

    public function test_review_author_and_property_owner_cannot_vote_for_themselves(): void
    {
        [$owner, $guest, , $review] = $this->publicReview();
        $url = route('user.reviews.helpful', $review);
        $this->actingAs($guest)->post($url)->assertForbidden();
        $this->actingAs($owner)->post($url)->assertForbidden();
        $this->assertDatabaseCount('review_helpful_votes', 0);
    }

    public function test_hidden_or_unverified_reviews_do_not_accept_votes(): void
    {
        [, , , $review] = $this->publicReview();
        $voter = User::factory()->create(['email_verified_at' => now()]);
        $url = route('user.reviews.helpful', $review);

        $review->update(['status' => 'hidden']);
        $this->actingAs($voter)->post($url)->assertNotFound();
        $review->update(['status' => 'approved', 'verified_stay' => false]);
        $this->actingAs($voter)->post($url)->assertNotFound();
    }
}
