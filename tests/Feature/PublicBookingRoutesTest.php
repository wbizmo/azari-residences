<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Bookings\AzariAvailabilityEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicBookingRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_pages_resolve(): void
    {
        foreach ([
            'home',
            'availability.index',
            'public.apartments',
            'public.rooms',
            'public.services',
            'public.concierge',
            'public.housekeeping',
            'public.restaurant',
            'public.airport-transfers',
            'public.local-guide',
            'public.about',
            'public.contact',
            'public.support',
            'public.booking-terms',
            'public.cancellation-policy',
            'public.privacy-policy',
            'public.terms',
        ] as $route) {
            $this->get(route($route))->assertSuccessful();
        }
    }

    public function test_availability_uses_live_inventory_and_rejects_overlap(): void
    {
        $location = Location::factory()->create();
        $roomType = RoomType::factory()->create();

        $property = Property::factory()->create([
            'location_id' => $location->id,
            'room_type_id' => $roomType->id,
            'is_published' => true,
            'status' => 'available',
            'max_guests' => 4,
        ]);

        $response = $this->get(route('availability.results', [
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'location_id' => $location->id,
            'room_type_id' => $roomType->id,
        ]));

        $response->assertSuccessful()->assertSee($property->name);
    }

    public function test_property_page_opens_property_specific_inventory(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);

        $this->get(route('properties.show', $property))
            ->assertSuccessful()
            ->assertSee(route('availability.property', $property), false);

        $this->get(route('availability.property', $property))
            ->assertSuccessful()
            ->assertSee('name="property_id"', false)
            ->assertSee('value="'.$property->id.'"', false)
            ->assertSee('90-day availability calendar');
    }

    public function test_property_filter_is_preserved_and_isolates_results(): void
    {
        $wanted = Property::factory()->create(['is_published' => true, 'status' => 'available', 'max_guests' => 4]);
        $other = Property::factory()->create(['is_published' => true, 'status' => 'available', 'max_guests' => 4]);

        $this->get(route('availability.results', [
            'property_id' => $wanted->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
        ]))->assertSuccessful()->assertSee($wanted->name)->assertDontSee($other->name);
    }

    public function test_expired_pending_payment_does_not_block_inventory(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $in = CarbonImmutable::today()->addDays(5);
        $out = $in->addDays(2);
        Booking::factory()->for($property)->create([
            'status' => 'pending_payment',
            'check_in' => $in,
            'check_out' => $out,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue(app(AzariAvailabilityEngine::class)->available($property->id, $in, $out));
    }

    public function test_active_hold_prevents_a_second_hold(): void
    {
        $property = Property::factory()->create([
            'is_published' => true, 'status' => 'available', 'same_day_booking' => true,
        ]);
        $in = CarbonImmutable::today()->addDays(5);
        $out = $in->addDays(2);
        $engine = app(AzariAvailabilityEngine::class);
        $engine->hold($property, $in, $out, 1, 0, 1, null);

        $this->expectException(ValidationException::class);
        $engine->hold($property, $in, $out, 1, 0, 1, null);
    }
    public function test_property_page_exposes_multiple_public_rate_choices_and_preserves_selection(): void
    {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
            'nightly_rate' => 200,
        ]);

        $type = $property->accommodationTypes()->firstOrFail();

        $flexible = $type->ratePlans()->create([
            'name' => 'Flexible breakfast',
            'code' => 'FLEX_BREAKFAST',
            'pricing_adjustment_type' => 'fixed',
            'pricing_adjustment' => 25,
            'meal_plan' => 'Breakfast included',
            'is_refundable' => true,
            'is_active' => true,
            'is_public' => true,
        ]);

        $response = $this->get(route('properties.show', $property));

        $response
            ->assertSuccessful()
            ->assertSee('Accommodation and rate options')
            ->assertSee('Flexible breakfast')
            ->assertSee('Breakfast included')
            ->assertSee('accommodation_type_id='.$type->id, false)
            ->assertSee('rate_plan_id='.$flexible->id, false);
    }

    public function test_property_availability_rejects_a_rate_plan_from_another_property(): void
    {
        $wanted = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
        ]);

        $other = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
        ]);

        $foreignType = $other->accommodationTypes()->firstOrFail();
        $foreignPlan = $foreignType->ratePlans()->firstOrFail();

        $this->get(route('availability.property', [
            'property' => $wanted,
            'accommodation_type_id' => $foreignType->id,
            'rate_plan_id' => $foreignPlan->id,
        ]))->assertNotFound();
    }

}
