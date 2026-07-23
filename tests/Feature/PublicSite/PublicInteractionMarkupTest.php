<?php

namespace Tests\Feature\PublicSite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInteractionMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_interaction_roots_are_present(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-modal', false)
            ->assertSee('data-drawer', false)
            ->assertSee('data-toast-region', false)
            ->assertSee('data-popover', false)
            ->assertSee('data-back-to-top', false);
    }

    public function test_booking_form_uses_custom_guest_selector(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-guest-selector', false)
            ->assertSee('data-stepper="adults"', false)
            ->assertSee('data-stepper="children"', false);
    }

    public function test_logo_slots_are_reserved_without_letter_monograms(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('brand-logo-slot', false)
            ->assertSee('footer-logo-slot', false)
            ->assertDontSee('brand-mark', false);
    }
}