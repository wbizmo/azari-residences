<?php

namespace Tests\Feature\PhaseTwo;

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

    public function test_map_endpoint_limits_deep_offset_and_invalid_geography(): void
    {
        $this->getJson(route('availability.map-points', [...$this->filters(), 'page' => 101]))
            ->assertUnprocessable();

        $this->getJson(route('availability.map-points', [
            ...$this->filters(), 'north' => 200,
        ]))->assertUnprocessable();
    }
}
