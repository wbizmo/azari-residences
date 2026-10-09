<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Location;
use App\Models\Property;
use App\Services\Search\DestinationSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationTypoSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_small_city_typo_falls_back_to_published_location(): void
    {
        $location = Location::factory()->create(['name' => 'Lagos', 'city' => 'Lagos']);
        Property::factory()->create([
            'location_id' => $location->id,
            'is_published' => true,
            'status' => 'available',
        ]);

        $matches = app(DestinationSearchService::class)->suggest('Lagaos');
        $this->assertSame($location->id, $matches->first()['id']);
    }

    public function test_accents_do_not_prevent_a_location_suggestion(): void
    {
        $location = Location::factory()->create([
            'name' => 'São Paulo',
            'city' => 'São Paulo',
            'country' => 'Brazil',
        ]);
        Property::factory()->create([
            'location_id' => $location->id,
            'is_published' => true,
            'status' => 'available',
        ]);

        $matches = app(DestinationSearchService::class)->suggest('Sao Paulo');
        $this->assertSame($location->id, $matches->first()['id']);
    }

    public function test_unpublished_locations_are_not_leaked_by_fuzzy_fallback(): void
    {
        $location = Location::factory()->create(['name' => 'Lagos', 'city' => 'Lagos']);
        Property::factory()->create([
            'location_id' => $location->id,
            'is_published' => false,
        ]);

        $this->assertTrue(app(DestinationSearchService::class)->suggest('Lagaos')->isEmpty());
    }
}
