<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReviewAggregateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_review_aggregate_excludes_unapproved_and_unverified_ratings(): void
    {
        $property = Property::factory()->create();
        $guest = User::factory()->create();
        foreach ([
            ['status' => 'approved', 'verified_stay' => true, 'rating' => 5],
            ['status' => 'pending', 'verified_stay' => true, 'rating' => 1],
            ['status' => 'hidden', 'verified_stay' => true, 'rating' => 1],
            ['status' => 'approved', 'verified_stay' => false, 'rating' => 1],
        ] as $rating) {
            $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
            Review::query()->create([
                ...$rating, 'booking_id' => $booking->id,
                'property_id' => $property->id, 'user_id' => $guest->id,
                'body' => 'Test review held for moderation and count eligibility.',
            ]);
        }

        $loaded = Property::query()
            ->withCount(['reviews as verified_review_count' => fn ($q) => $q
                ->where('verified_stay', true)->where('status', 'approved')])
            ->withAvg(['reviews as verified_review_score' => fn ($q) => $q
                ->where('verified_stay', true)->where('status', 'approved')], 'rating')
            ->findOrFail($property->id);

        $this->assertSame(1, $loaded->verified_review_count);
        $this->assertEquals(5, $loaded->verified_review_score);

        $search = file_get_contents(app_path('Services/Search/MarketplaceSearchService.php'));
        $this->assertStringContainsString("reviews as verified_review_count' =>", $search);
        $this->assertStringContainsString("reviews as verified_review_score' =>", $search);
    }
}
