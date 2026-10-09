<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPropertyStatusParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unavailable_and_unpublished_properties_cannot_bypass_search_via_direct_url(): void
    {
        foreach (['inactive', 'unavailable', 'maintenance', 'archived'] as $status) {
            $property = Property::factory()->create([
                'status' => $status,
                'is_published' => true,
            ]);
            $this->get(route('properties.show', $property))->assertNotFound();
        }
        $draft = Property::factory()->create(['is_published' => false]);
        $this->get(route('properties.show', $draft))->assertNotFound();
    }

    public function test_unavailable_properties_cannot_issue_public_quotes(): void
    {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'maintenance',
        ]);
        $this->postJson(route('azari.availability.quote', $property), [
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
        ])->assertNotFound();
    }
}
