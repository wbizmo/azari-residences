<?php

namespace Tests\Feature\PublicSite;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintTwoPointFiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_uses_database_featured_properties(): void
    {
        $property = Property::query()->create([
            'name' => 'Featured Test Residence',
            'slug' => 'featured-test-residence',
            'location' => 'Kigali',
            'country' => 'Rwanda',
            'property_type' => 'Apartment',
            'bedrooms' => 2,
            'bathrooms' => 2,
            'max_guests' => 4,
            'nightly_rate' => 300000,
            'currency' => 'USD',
            'is_featured' => true,
            'is_published' => true,
        ]);

        $this->get('/')->assertOk()->assertSee($property->name);
    }

    public function test_unpublished_property_cannot_be_viewed(): void
    {
        $property = Property::query()->create([
            'name' => 'Hidden Residence',
            'slug' => 'hidden-residence',
            'location' => 'Abuja',
            'country' => 'Nigeria',
            'property_type' => 'Apartment',
            'bedrooms' => 2,
            'bathrooms' => 2,
            'max_guests' => 4,
            'nightly_rate' => 300000,
            'currency' => 'USD',
            'is_published' => false,
        ]);

        $this->get(route('properties.show', $property))->assertNotFound();
    }

    public function test_regular_user_cannot_access_staff_admin(): void
    {
        $user = User::factory()->create(['staff_role' => null]);

        $this->actingAs($user)->get('/azaridevadmin')->assertNotFound();
    }

    public function test_active_administrator_can_access_staff_admin(): void
    {
        $user = User::factory()->create([
            'staff_role' => 'administrator',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/azaridevadmin')->assertOk();
    }

    public function test_public_pages_contain_preloader(): void
    {
        $this->get('/')->assertOk()->assertSee('data-public-preloader', false);
    }
}
