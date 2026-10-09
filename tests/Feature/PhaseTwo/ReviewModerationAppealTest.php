<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Review;
use App\Models\ReviewAppeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewModerationAppealTest extends TestCase
{
    use RefreshDatabase;

    private function hiddenReview(): array
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id]);
        $review = Review::query()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'user_id' => $guest->id,
            'rating' => 3,
            'body' => 'The room was not as described on the booking listing.',
            'status' => 'hidden',
            'verified_stay' => true,
            'moderation_reason' => 'The text may require a manual review.',
        ]);
        return [$guest, $booking, $review];
    }

    public function test_verified_guest_can_appeal_hidden_review_only_once(): void
    {
        [$guest, $booking, $review] = $this->hiddenReview();
        $url = route('user.reviews.appeal', $booking);
        $this->actingAs($guest)->post($url, [
            'reason' => 'My review is factual and relevant to my completed stay.',
        ])->assertRedirect();

        $this->assertDatabaseHas('review_appeals', [
            'review_id' => $review->id,
            'user_id' => $guest->id,
            'status' => 'pending',
        ]);
        $this->actingAs($guest)->post($url, [
            'reason' => 'I am attempting to submit a second review appeal.',
        ])->assertUnprocessable();

        $this->assertSame(1, ReviewAppeal::query()->where('review_id', $review->id)->count());
    }

    public function test_unrelated_guest_cannot_appeal_someone_elses_review(): void
    {
        [, $booking, $review] = $this->hiddenReview();
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($outsider)->post(route('user.reviews.appeal', $booking), [
            'reason' => 'An attacker trying to appeal on someone else behalf.',
        ])->assertForbidden();
        $this->assertDatabaseMissing('review_appeals', ['review_id' => $review->id]);
    }

    public function test_authorized_admin_can_restore_review_without_altering_guest_text(): void
    {
        [$guest, $booking, $review] = $this->hiddenReview();
        $this->actingAs($guest)->post(route('user.reviews.appeal', $booking), [
            'reason' => 'Please recheck the evidence in my original guest review.',
        ])->assertRedirect();

        $moderator = User::factory()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
            'staff_role' => 'administrator',
        ]);
        $originalBody = $review->body;
        $url = route('azari.admin.reviews.appeal', $review);
        $this->actingAs($moderator)->post($url, [
            'decision' => 'accepted',
            'decision_note' => 'Guest review reinstated after a staff check.',
        ])->assertRedirect();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertSame($originalBody, $review->fresh()->body);
        $this->assertSame('accepted', $review->fresh()->appeal->status);
        $this->actingAs($moderator)->post($url, [
            'decision' => 'accepted',
        ])->assertUnprocessable();
    }
}
