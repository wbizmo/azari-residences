<?php

namespace Tests\Feature\PublicSite;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BatchFiveHomeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_only_merchandises_real_published_available_accommodation(): void
    {
        $featured = Property::factory()->create([
            'name' => 'Verified Bay Apartment',
            'is_featured' => true,
            'is_published' => true,
            'status' => 'available',
        ]);
        $inactive = Property::factory()->create([
            'name' => 'Closed Bay Apartment',
            'is_featured' => true,
            'is_published' => true,
            'status' => 'maintenance',
        ]);
        $draft = Property::factory()->create([
            'name' => 'Private Bay Apartment',
            'is_featured' => true,
            'is_published' => false,
            'status' => 'available',
        ]);

        $response = $this->get(route('home'));
        $response->assertOk()
            ->assertSee('Verified Bay Apartment')
            ->assertDontSee('Closed Bay Apartment')
            ->assertDontSee('Private Bay Apartment')
            ->assertSee('Find available stays')
            ->assertSee('Check dates for live rates')
            ->assertSee('images/resavar-hero.png')
            ->assertSee('google-play-badge.png')
            ->assertSee('name="rooms"', false)
            ->assertSee('name="adults"', false)
            ->assertSee('name="children"', false)
            ->assertSee('name="destination"', false)
            ->assertSee('name="check_in"', false)
            ->assertSee('name="check_out"', false)
            ->assertSee('data-resavar-mobile-search-link', false);

        $markup = $response->getContent();
        $this->assertLessThan(strpos($markup, 'name="check_in"'), strpos($markup, 'name="destination"'));
        $this->assertStringNotContainsString('name="flight"', $markup);
    }

    public function test_autocomplete_hides_unavailable_and_unpublished_inventory(): void
    {
        $available = Property::factory()->create([
            'name' => 'Sunny Bay Hotel', 'is_published' => true, 'status' => 'available',
        ]);
        Property::factory()->create([
            'name' => 'Sunny Bay Blocked', 'is_published' => true, 'status' => 'maintenance',
        ]);
        Property::factory()->create([
            'name' => 'Sunny Bay Private', 'is_published' => false, 'status' => 'available',
        ]);

        $this->getJson(route('destinations.suggest', ['q' => 'Sunny Bay']))
            ->assertOk()
            ->assertJsonFragment(['type' => 'property', 'id' => $available->id,
                'label' => 'Sunny Bay Hotel'])
            ->assertDontSee('Sunny Bay Blocked')
            ->assertDontSee('Sunny Bay Private');

        $available->update(['is_published' => false, 'status' => 'draft']);
        $revoked = $this->getJson(route('destinations.suggest', ['q' => 'Sunny Bay']))
            ->assertOk()
            ->assertDontSee('Sunny Bay Hotel');
        // HTTP middleware may normalize directive order; verify the actual
        // no-store guarantee rather than comparing order-dependent strings.
        $this->assertStringContainsString(
            'no-store',
            (string) $revoked->headers->get('Cache-Control')
        );

    }

    public function test_homepage_query_preserves_destination_dates_and_guest_counts_in_canonical_results_url(): void
    {
        $property = Property::factory()->create([
            'name' => 'Harbour Estate Hotel', 'is_published' => true, 'status' => 'available',
        ]);
        $start = now()->addDays(8)->toDateString();
        $end = now()->addDays(11)->toDateString();
        $response = $this->get(route('availability.search', [
            'check_in' => $start, 'check_out' => $end,
            'adults' => 3, 'children' => 1, 'rooms' => 2,
            'destination' => $property->name,
        ]));

        $response->assertRedirectContains('check_in='.$start)
            ->assertRedirectContains('check_out='.$end)
            ->assertRedirectContains('adults=3')
            ->assertRedirectContains('children=1')
            ->assertRedirectContains('rooms=2');
        $this->assertStringContainsString('destination=', $response->headers->get('Location'));
        $this->assertStringContainsString('destination_type=property', $response->headers->get('Location'));
        $this->assertStringContainsString('destination_id='.$property->id, $response->headers->get('Location'));
    }

    public function test_homepage_search_rejects_invalid_stay_and_guest_ranges(): void
    {
        $this->get(route('availability.search', [
            'check_in' => now()->addDays(9)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'adults' => 0, 'rooms' => 0,
        ]))->assertRedirect()->assertSessionHasErrors(['check_out', 'adults', 'rooms']);
    }

    public function test_homepage_design_rules_keep_progressive_fallback_and_live_supply_copy(): void
    {
        $home = file_get_contents(resource_path('views/public/home.blade.php'));
        $this->assertStringContainsString('data-destination-search', $home);
        $this->assertStringContainsString('role="combobox"', $home);
        $this->assertStringContainsString('data-destination-list', $home);
        $this->assertStringContainsString('<noscript>', $home);
        $this->assertStringContainsString('data-resavar-mobile-search-link', $home);
        $this->assertStringNotContainsString('number_format($property->nightly_rate)', $home);
        $this->assertStringContainsString('google-play-badge.png', $home);

        $styles = file_get_contents(resource_path('css/reserva/home-search.css'));
        $this->assertStringContainsString('max-width: 460px', $styles);
        $this->assertStringContainsString('prefers-reduced-motion', $styles);
        $scripts = file_get_contents(resource_path('js/app.js'));
        $this->assertStringNotContainsString("const minimumCheckout = nextDay.toISOString()", $scripts);
        $this->assertStringContainsString('requestSequence++', $scripts);
    }
}
