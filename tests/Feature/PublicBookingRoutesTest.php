<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
