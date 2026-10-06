<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingGuestVerificationInvite;
use App\Models\BookingHold;
use App\Models\IdentityVerification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SeamlessBookingOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('azari.identity.dojah.enabled', true);
        config()->set('azari.identity.dojah.secret_key', 'test-dojah-secret');
        config()->set('azari.identity.dojah.widget_id', '6a900eb834b449c195ebdbb5');
        config()->set('azari.identity.dojah.token_id', 'different-token-id');
        config()->set('azari.identity.dojah.required_steps', []);

        Mail::fake();
    }

    public function test_unregistered_guest_can_enter_details_before_account_creation(): void
    {
        $property = Property::factory()->create();
        $hold = BookingHold::query()->create([
            'property_id' => $property->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('azari.booking.checkout', $hold->token))
            ->assertOk()
            ->assertSee('Your booking details')
            ->assertSee('Secure your Resarva access');

        $response = $this->post(route('azari.booking.onboarding.begin', $hold->token), [
            'hold_token' => $hold->token,
            'first_name' => 'Lead',
            'last_name' => 'Guest',
            'guest_email' => 'lead@example.com',
            'guest_phone' => '+2348000000000',
            'nationality' => 'Nigerian',
            'address' => '1 Azari Street',
            'city' => 'Lagos',
            'country' => 'Nigeria',
            'adults' => [
                ['first_name' => 'Lead', 'last_name' => 'Guest'],
                [
                    'first_name' => 'Second',
                    'last_name' => 'Adult',
                    'email' => 'second@example.com',
                ],
            ],
            'terms' => '1',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $user = User::query()->where('email', 'lead@example.com')->firstOrFail();

        $response->assertRedirect(route('azari.booking.onboarding.email', $hold->token));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);

        $hold->refresh();
        $this->assertSame($user->id, $hold->user_id);
        $this->assertSame('second@example.com', data_get($hold->guest_draft, 'adults.1.email'));
        $this->assertTrue($hold->expires_at->gt(now()->addMinutes(30)));
        $this->assertNotEmpty($hold->email_code_hash);
    }

    public function test_existing_email_is_bound_to_existing_account_without_duplicate_creation(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $property = Property::factory()->create();
        $hold = BookingHold::query()->create([
            'property_id' => $property->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'rooms' => 1,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->post(route('azari.booking.onboarding.begin', $hold->token), [
            'hold_token' => $hold->token,
            'first_name' => 'Existing',
            'last_name' => 'Guest',
            'guest_email' => 'existing@example.com',
            'guest_phone' => '+2348000000000',
            'nationality' => 'Nigerian',
            'address' => '1 Azari Street',
            'city' => 'Lagos',
            'country' => 'Nigeria',
            'adults' => [
                ['first_name' => 'Existing', 'last_name' => 'Guest'],
            ],
            'terms' => '1',
            'password' => 'IgnoredPass123!',
            'password_confirmation' => 'IgnoredPass123!',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
        $this->assertSame($existing->id, $hold->fresh()->user_id);
    }

    public function test_public_guest_verification_url_does_not_expose_guest_details_before_email_code(): void
    {
        [$booking, $guest] = $this->bookingWithExtraAdult();

        $url = route('guest-verification.show', [$booking->reference, $guest->position], false);

        $this->assertSame(
            '/guest-verification/'.$booking->reference.'/'.$guest->position,
            $url
        );
        $this->assertStringNotContainsString('?', $url);

        $this->get($url)
            ->assertOk()
            ->assertSee($booking->reference)
            ->assertDontSee($guest->full_name)
            ->assertDontSee($guest->email);
    }

    public function test_email_code_unlocks_only_that_adults_dojah_page(): void
    {
        [$booking, $guest] = $this->bookingWithExtraAdult();

        BookingGuestVerificationInvite::query()->create([
            'booking_guest_id' => $guest->id,
            'email' => $guest->email,
            'code_hash' => Hash::make('123456'),
            'code_expires_at' => now()->addMinutes(10),
            'code_sent_at' => now(),
        ]);

        $this->post(
            route('guest-verification.code.verify', [$booking->reference, $guest->position]),
            ['code' => '123456']
        )->assertRedirect(
            route('guest-verification.show', [$booking->reference, $guest->position])
        );

        $this->get(route('guest-verification.show', [$booking->reference, $guest->position]))
            ->assertOk()
            ->assertSee($guest->full_name)
            ->assertSee($guest->email)
            ->assertSee('Start secure verification');
    }

    public function test_extra_adult_can_optionally_create_account_and_guest_kyc_promotes_to_it(): void
    {
        [$booking, $guest] = $this->bookingWithExtraAdult();

        $grantKey = 'azari_guest_verification_grants.'.$guest->id;

        $response = $this
            ->withSession([$grantKey => now()->addMinutes(30)->timestamp])
            ->post(
                route('guest-verification.account.create', [$booking->reference, $guest->position]),
                [
                    'password' => 'StrongPass123!',
                    'password_confirmation' => 'StrongPass123!',
                ]
            );

        $user = User::query()->where('email', $guest->email)->firstOrFail();

        $response->assertRedirect(
            route('guest-verification.show', [$booking->reference, $guest->position])
        );

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame($user->id, $guest->fresh()->user_id);

        $guestVerification = IdentityVerification::query()->create([
            'booking_guest_id' => $guest->id,
            'user_id' => $user->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => 'extra-adult-verified',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        app(\App\Services\Identity\DojahService::class)
            ->syncVerifiedGuestToUser($guest->fresh(), $user->fresh());

        $this->assertTrue(IdentityVerification::userIsVerified($user->id));
        $this->assertTrue($guestVerification->isVerified());
    }

    private function bookingWithExtraAdult(): array
    {
        $booking = Booking::factory()->create([
            'reference' => 'AZR-TEST-EXTRA',
            'user_id' => User::factory()->create()->id,
            'status' => 'pending_payment',
        ]);

        BookingGuest::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'type' => 'adult',
            'position' => 1,
            'first_name' => 'Lead',
            'last_name' => 'Guest',
            'email' => 'lead@example.com',
            'is_lead' => true,
        ]);

        $guest = BookingGuest::query()->create([
            'booking_id' => $booking->id,
            'type' => 'adult',
            'position' => 2,
            'first_name' => 'Second',
            'last_name' => 'Adult',
            'email' => 'second@example.com',
            'is_lead' => false,
        ]);

        return [$booking, $guest];
    }
}
