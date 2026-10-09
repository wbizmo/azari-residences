<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DojahRoutingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_identity_and_guest_email_code_routes_are_registered(): void
    {
        foreach ([
            'user.identity.index', 'user.identity.status', 'guest-verification.show',
            'guest-verification.code.send', 'guest-verification.code.verify',
            'guest-verification.account.create', 'guest-verification.account.link',
            'guest-verification.status', 'webhooks.dojah',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing identity journey route {$name}.");
        }
    }

    public function test_public_guest_link_does_not_reveal_identity_before_email_code(): void
    {
        $booking = Booking::factory()->create(['user_id' => User::factory()->create()->id]);
        $guest = BookingGuest::query()->create([
            'booking_id' => $booking->getKey(), 'type' => 'adult', 'position' => 2,
            'first_name' => 'Ada', 'last_name' => 'Okoro', 'email' => 'private@example.test',
            'is_lead' => false,
        ]);

        $this->get(route('guest-verification.show', [$booking->reference, $guest->position]))
            ->assertOk()->assertDontSee('private@example.test')->assertDontSee('Ada Okoro');
        $this->getJson(route('guest-verification.status', [$booking->reference, $guest->position]))
            ->assertForbidden();
    }
}
