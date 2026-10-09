<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCurrencyPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_save_currency_without_resubmitting_phone_timezone_or_email(): void
    {
        $guest = User::factory()->create([
            'display_currency' => 'USD',
            'phone' => null,
            'timezone' => null,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($guest)
            ->patch(route('user.profile.currency'), ['display_currency' => 'NGN'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('NGN', $guest->fresh()->display_currency);
        $this->assertNull($guest->fresh()->phone);
        $this->assertNull($guest->fresh()->timezone);

        $this->actingAs($guest)
            ->get(route('user.profile.edit'))
            ->assertOk()
            ->assertSee('NGN · Nigerian Naira');

        $this->actingAs($guest)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('dashboard-display-currency')
            ->assertSee('Preference only.');
    }

    public function test_unsupported_currency_fails_closed_and_leaves_setting_unchanged(): void
    {
        $guest = User::factory()->create(['display_currency' => 'USD', 'email_verified_at' => now()]);

        $this->actingAs($guest)
            ->from(route('user.dashboard'))
            ->patch(route('user.profile.currency'), ['display_currency' => 'FAKE'])
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHasErrors('display_currency');

        $this->assertSame('USD', $guest->fresh()->display_currency);
    }

    public function test_currency_preference_only_updates_authenticated_user(): void
    {
        $guest = User::factory()->create(['display_currency' => 'USD', 'email_verified_at' => now()]);
        $other = User::factory()->create(['display_currency' => 'USD', 'email_verified_at' => now()]);

        $this->actingAs($guest)
            ->patch(route('user.profile.currency'), [
                'display_currency' => 'GBP',
                'user_id' => $other->getKey(), // ignored: the endpoint has no user parameter
            ])
            ->assertRedirect();

        $this->assertSame('GBP', $guest->fresh()->display_currency);
        $this->assertSame('USD', $other->fresh()->display_currency);
    }

    public function test_display_currency_change_does_not_reprice_existing_bookings_or_payments(): void
    {
        $guest = User::factory()->create(['display_currency' => 'USD', 'email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->getKey(),
            'currency' => 'USD',
            'total' => 750.25,
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->getKey(),
            'provider' => 'manual',
            'reference' => 'PAY-PHASE2-CURRENCY-PREFERENCE',
            'status' => Payment::SUCCESSFUL,
            'currency' => 'USD',
            'amount' => 750.25,
            'verified_at' => now(),
        ]);

        $this->actingAs($guest)
            ->patch(route('user.profile.currency'), ['display_currency' => 'CAD'])
            ->assertRedirect();

        $this->assertSame('CAD', $guest->fresh()->display_currency);
        $this->assertSame('USD', $booking->fresh()->currency);
        $this->assertEqualsWithDelta(750.25, (float) $booking->fresh()->total, 0.001);
        $this->assertSame('USD', $payment->fresh()->currency);
        $this->assertEqualsWithDelta(750.25, (float) $payment->fresh()->amount, 0.001);
    }
}
