<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\AccommodationType;
use App\Models\Property;
use App\Services\Search\MarketplaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressiveMapQuoteEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function filters(): array
    {
        return [
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'adults' => 2,
            'rooms' => 1,
        ];
    }

    public function test_map_endpoint_returns_same_server_eligibility_contract_as_list(): void
    {
        $response = $this->getJson(route('availability.map-points', $this->filters()))->assertOk();
        $response->assertJsonStructure(['points', 'page', 'total', 'next_page']);
        $this->assertSame([], $response->json('points'));
    }

    public function test_cursor_map_returns_stable_continuation_contract_without_deep_offset(): void
    {
        $response = $this->getJson(route('availability.map-cursor', $this->filters()))
            ->assertOk()
            ->assertJsonStructure(['points', 'next_cursor', 'batch_size']);
        $this->assertSame([], $response->json('points'));
        $this->assertNull($response->json('next_cursor'));
        $this->assertSame(20, $response->json('batch_size'));

        $this->getJson(route('availability.map-cursor', [
            ...$this->filters(), 'cursor' => 'bad<>token',
        ]))->assertUnprocessable();
    }

    public function test_map_selection_quotes_exactly_the_same_type_and_total_as_list(): void
    {
        $property = Property::factory()->create([
            'status' => 'active', 'is_published' => true,
            'latitude' => 6.5244, 'longitude' => 3.3792,
        ]);
        // A more expensive room is inserted first; map and list must use
        // the same base-rate ordering rather than insertion/ID ordering.
        foreach ([27000, 15000] as $i => $price) {
            AccommodationType::query()->create([
                'property_id' => $property->id,
                'name' => 'Room variant '.($i + 1),
                'slug' => 'room-variant-'.($i + 1),
                'code' => 'MAP'.($i + 1),
                'adult_capacity' => 2, 'child_capacity' => 0, 'max_guests' => 2,
                'total_inventory' => 3,
                'base_rate' => $price,
                'currency' => 'USD',
                'is_active' => true, 'is_published' => true,
            ]);
        }

        $market = app(MarketplaceSearchService::class);
        $filters = [...$this->filters(), 'children' => 0, 'sort' => 'recommended'];
        $list = $market->search($filters, false)['results']->getCollection()->first();
        $pins = $market->mapCursor($filters)['points'];

        $this->assertNotNull($list);
        $this->assertCount(1, $pins);
        $this->assertSame($property->id, $pins[0]['id']);
        $this->assertEqualsWithDelta((float) $list['quote']['total'], $pins[0]['price'], 0.005);
        $this->assertSame($list['quote']['currency'], $pins[0]['currency']);
    }

    public function test_map_cursor_rejects_unsigned_scope_and_incomplete_viewports(): void
    {
        $this->getJson(route('availability.map-cursor', [
            ...$this->filters(), 'cursor' => 'eyJpZCI6MX0.invalid',
        ]))->assertUnprocessable();

        foreach (['north' => 8, 'south' => 5, 'west' => 3, 'east' => 6] as $edge => $value) {
            $this->getJson(route('availability.map-cursor', [
                ...$this->filters(), $edge => $value,
            ]))->assertUnprocessable();
        }
    }

    public function test_map_endpoint_limits_deep_offset_and_invalid_geography(): void
    {
        $this->getJson(route('availability.map-points', [...$this->filters(), 'page' => 101]))
            ->assertUnprocessable();

        $this->getJson(route('availability.map-points', [
            ...$this->filters(), 'north' => 200,
        ]))->assertUnprocessable();
    }
}
