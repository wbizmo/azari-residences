<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewPublicationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPrivacyAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_details_guard_detects_private_contact_data(): void
    {
        $guard = app(ReviewPublicationGuard::class);

        $this->assertTrue($guard->containsContactDetails(['body' => 'Contact me at guest@example.com']));
        $this->assertTrue($guard->containsContactDetails(['positive_feedback' => 'Call +234 801 234 5678']));
        $this->assertTrue($guard->containsContactDetails(['negative_feedback' => 'See https://example.com/contact']));
        $this->assertFalse($guard->containsContactDetails(['body' => 'Great five-night stay from 2026-10-10 to 2026-10-15.']));
    }

    public function test_directory_can_filter_declared_review_language_without_exposing_other_reviews(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'active']);

        foreach (['fr' => 'FRENCH_REVIEW', 'en' => 'ENGLISH_REVIEW'] as $language => $body) {
            $guest = User::factory()->create();
            $booking = Booking::factory()->create(['property_id' => $property->id, 'user_id' => $guest->id]);
            Review::query()->create([
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'user_id' => $guest->id,
                'rating' => 5,
                'body' => $body.' makes this a genuine review.',
                'status' => 'approved',
                'verified_stay' => true,
                'language' => $language,
            ]);
        }

        $this->get(route('properties.reviews', [$property, 'language' => 'fr']))
            ->assertOk()->assertSeeText('FRENCH_REVIEW')
            ->assertDontSeeText('ENGLISH_REVIEW');

        $this->get(route('properties.reviews', [$property, 'language' => 'invalid-locale']))
            ->assertSessionHasErrors('language');
    }
}
