<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\BookingShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BookingItineraryShareTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedStay(): array
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->id,
            'status' => 'confirmed',
            'guest_name' => 'PRIVATE_LEAD_GUEST',
            'guest_email' => 'private.share@example.test',
            'property_name_snapshot' => 'Visible Property Name',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'nights' => 2,
        ]);
        return [$guest, $booking];
    }

    public function test_guest_can_create_private_expiring_share_without_leaking_identity_or_payment(): void
    {
        [$guest, $booking] = $this->confirmedStay();
        $response = $this->actingAs($guest)
            ->post(route('user.bookings.share.create', $booking->reference))->assertRedirect();
        $url = $response->getSession()->get('itinerary_share_url');
        $this->assertIsString($url);
        $this->assertSame(64, strlen(basename(parse_url($url, PHP_URL_PATH))));

        $share = BookingShareLink::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertNotSame(basename(parse_url($url, PHP_URL_PATH)), $share->token_hash);
        $this->assertTrue($share->expires_at->isFuture());

        Auth::logout();
        $public = $this->get($url)->assertOk()->assertSeeText('Visible Property Name');
        $public->assertDontSee('PRIVATE_LEAD_GUEST')
            ->assertDontSee('private.share@example.test')
            ->assertDontSee($booking->reference);
        $this->assertStringContainsString('private', $public->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $public->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex', $public->headers->get('X-Robots-Tag'));
    }

    public function test_owner_reissue_and_revocation_invalidate_previous_links(): void
    {
        [$guest, $booking] = $this->confirmedStay();
        $first = $this->actingAs($guest)
            ->post(route('user.bookings.share.create', $booking->reference));
        $firstUrl = $first->getSession()->get('itinerary_share_url');

        $second = $this->actingAs($guest)
            ->post(route('user.bookings.share.create', $booking->reference));
        $secondUrl = $second->getSession()->get('itinerary_share_url');
        $this->assertNotSame($firstUrl, $secondUrl);
        $this->get($firstUrl)->assertNotFound();
        $this->get($secondUrl)->assertOk();

        $this->actingAs($guest)
            ->delete(route('user.bookings.share.revoke', $booking->reference))->assertRedirect();
        $this->get($secondUrl)->assertNotFound();
    }

    public function test_unrelated_user_cannot_issue_share_for_someone_elses_booking(): void
    {
        [, $booking] = $this->confirmedStay();
        $attacker = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($attacker)
            ->post(route('user.bookings.share.create', $booking->reference))->assertNotFound();
        $this->assertDatabaseCount('booking_share_links', 0);
    }
}
