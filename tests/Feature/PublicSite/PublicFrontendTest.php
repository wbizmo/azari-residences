<?php

namespace Tests\Feature\PublicSite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_public_frontend(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Exceptional stays, thoughtfully managed.')
            ->assertSee('Find your residence')
            ->assertSee('Back to top', false);
    }

    public function test_homepage_does_not_render_the_removed_hero_side_panel(): void
    {
        $this->get('/')
            ->assertDontSee('Live residence availability')
            ->assertDontSee('Manage every detail in one place');
    }

    public function test_public_navigation_contains_required_primary_actions(): void
    {
        $this->get('/')
            ->assertSee('Residences')
            ->assertSee('Services')
            ->assertSee('Verify booking')
            ->assertSee('Book now');
    }

    public function test_availability_search_requires_valid_dates(): void
    {
        $this->get('/availability/search')
            ->assertSessionHasErrors(['check_in', 'check_out']);
    }

    public function test_availability_search_rejects_checkout_before_checkin(): void
    {
        $this->get('/availability/search?check_in=2030-08-10&check_out=2030-08-09&adults=2&children=0')
            ->assertSessionHasErrors(['check_out']);
    }

    public function test_valid_availability_search_renders_the_search_shell(): void
    {
        $this->get('/availability/search?check_in=2030-08-10&check_out=2030-08-14&adults=2&children=1&location=Ikoyi&property_type=apartment')
            ->assertOk()
            ->assertSee('Your stay request')
            ->assertSee('4 nights')
            ->assertSee('3 guests')
            ->assertSee('Ikoyi');
    }

    public function test_guest_limits_are_enforced(): void
    {
        $this->get('/availability/search?check_in=2030-08-10&check_out=2030-08-14&adults=13&children=9')
            ->assertSessionHasErrors(['adults', 'children']);
    }
}
