<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerReviewReplyModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_owner_reply_requires_moderation_before_publication(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
        $review = Review::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $property->id,
            'user_id' => $guest->id,
            'rating' => 5,
            'body' => 'An excellent stay with friendly staff.',
            'status' => 'approved',
            'verified_stay' => true,
        ]);

        $this->actingAs($owner)->post(route('user.owner.phase2.reviews.reply', [$property, $review]), [
            'reply' => 'Thank you for staying with us.',
        ])->assertRedirect();

        $this->assertSame('pending', $review->fresh()->owner_reply_status);
        $view = file_get_contents(resource_path('views/public/properties/show.blade.php'));
        $this->assertStringContainsString("owner_reply_status === 'approved'", $view);
    }

    public function test_admin_review_form_contains_separate_property_reply_moderation(): void
    {
        $view = file_get_contents(resource_path('views/admin/reviews/index.blade.php'));
        $this->assertStringContainsString('owner_reply_status', $view);
        $this->assertStringContainsString('rejected', $view);
    }
}
