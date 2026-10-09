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
            ->assertSee('Check availability')
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
            ->assertSee('Apartments')
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

    public function test_valid_availability_search_redirects_to_the_canonical_results_route(): void
    {
        $response = $this->get('/availability/search?check_in=2030-08-10&check_out=2030-08-14&adults=2&children=1&location=Ikoyi&property_type=apartment');

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/availability/results', $location);
        $this->assertStringContainsString('check_in=2030-08-10', $location);
        $this->assertStringContainsString('check_out=2030-08-14', $location);
        $this->assertStringContainsString('adults=2', $location);
        $this->assertStringContainsString('children=1', $location);
    }

    public function test_guest_limits_are_enforced(): void
    {
        $this->get('/availability/search?check_in=2030-08-10&check_out=2030-08-14&adults=13&children=9')
            ->assertSessionHasErrors(['adults', 'children']);
    }
}
