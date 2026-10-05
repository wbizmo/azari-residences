<?php

namespace Tests\Feature;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\InventoryDate;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\RatePlan;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Search\DestinationSearchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservaMarketplaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quantity_inventory_counts_booked_rooms_instead_of_blocking_the_whole_property(): void
    {
        [$property, $type] = $this->inventoryProperty(3);
        $in = CarbonImmutable::today()->addDays(10);
        $out = $in->addDays(2);

        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $type->id,
            'status' => 'confirmed',
            'check_in' => $in,
            'check_out' => $out,
            'rooms' => 2,
        ]);

        $engine = app(AzariAvailabilityEngine::class);

        $this->assertSame(1, $engine->availableQuantity($type, $in, $out));
        $this->assertTrue($engine->availableForProperty($property, $in, $out, 1, $type->id));
        $this->assertFalse($engine->availableForProperty($property, $in, $out, 2, $type->id));
    }

    public function test_overlapping_holds_cannot_oversell_quantity_inventory(): void
    {
        [$property, $type, $ratePlan] = $this->inventoryProperty(3, true);
        $in = CarbonImmutable::today()->addDays(12);
        $out = $in->addDays(3);
        $engine = app(AzariAvailabilityEngine::class);

        $first = $engine->hold($property, $in, $out, 2, 0, 2, null, $type->id, $ratePlan->id);

        $this->assertSame(2, $first->rooms);
        $this->assertSame(1, $engine->availableQuantity($type, $in, $out));

        try {
            $engine->hold($property, $in, $out, 2, 0, 2, null, $type->id, $ratePlan->id);
            $this->fail('A second two-unit hold should not fit into one remaining unit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rooms', $exception->errors());
        }

        $lastUnit = $engine->hold($property, $in, $out, 1, 0, 1, null, $type->id, $ratePlan->id);

        $this->assertSame(1, $lastUnit->rooms);
        $this->assertSame(0, $engine->availableQuantity($type, $in, $out));
    }

    public function test_stop_sell_date_makes_the_entire_requested_range_unavailable(): void
    {
        [$property, $type] = $this->inventoryProperty(5);
        $in = CarbonImmutable::today()->addDays(15);
        $out = $in->addDays(3);

        InventoryDate::query()->create([
            'accommodation_type_id' => $type->id,
            'date' => $in->addDay(),
            'stop_sell' => true,
        ]);

        $engine = app(AzariAvailabilityEngine::class);

        $this->assertSame(0, $engine->availableQuantity($type, $in, $out));
        $this->assertFalse($engine->availableForProperty($property, $in, $out, 1, $type->id));
    }

    public function test_pricing_rules_quantity_and_service_charge_are_applied_deterministically(): void
    {
        [$property, $type, $ratePlan] = $this->inventoryProperty(4, true, [
            'base_rate' => 100,
            'cleaning_fee' => 20,
            'service_charge' => 10,
            'tax_rate' => 0,
        ]);

        $in = CarbonImmutable::today()->addDays(20);
        $out = $in->addDays(2);

        PricingRule::query()->create([
            'property_id' => $property->id,
            'accommodation_type_id' => $type->id,
            'rate_plan_id' => $ratePlan->id,
            'name' => 'Demand uplift',
            'rule_type' => 'increase',
            'adjustment_type' => 'increase',
            'starts_on' => $in,
            'ends_on' => $out,
            'amount' => 0,
            'percentage' => 10,
            'priority' => 100,
            'is_active' => true,
        ]);

        $quote = app(AzariPricingEngine::class)->quote(
            $property,
            $in,
            $out,
            [],
            $type,
            $ratePlan,
            2
        );

        $this->assertSame('USD', $quote['currency']);
        $this->assertSame(2, $quote['quantity']);
        $this->assertSame(2, $quote['nights']);
        $this->assertEqualsWithDelta(110.0, $quote['nightly_rate'], 0.001);
        $this->assertEqualsWithDelta(440.0, $quote['subtotal'], 0.001);
        $this->assertEqualsWithDelta(50.0, $quote['fee_total'], 0.001);
        $this->assertEqualsWithDelta(490.0, $quote['total'], 0.001);
        $this->assertSame($ratePlan->id, $quote['rate_plan_id']);
        $this->assertNotEmpty($quote['nightly_breakdown'][0]['pricing_rules']);
    }

    public function test_destination_suggestions_are_bounded_and_match_index_friendly_prefixes(): void
    {
        $location = Location::factory()->create([
            'name' => 'Victoria Island',
            'city' => 'Lagos',
            'country' => 'Nigeria',
        ]);

        Property::factory()->create([
            'location_id' => $location->id,
            'name' => 'Reserva Victoria Grand',
            'slug' => 'reserva-victoria-grand',
            'is_published' => true,
        ]);

        $service = app(DestinationSearchService::class);

        $locationResults = $service->suggest('Vic', 8);
        $propertyResults = $service->suggest('Reserva', 8);

        $this->assertTrue($locationResults->contains(
            fn (array $item) => $item['type'] === 'location'
                && $item['label'] === 'Victoria Island'
        ));

        $this->assertTrue($propertyResults->contains(
            fn (array $item) => $item['type'] === 'property'
                && $item['label'] === 'Reserva Victoria Grand'
        ));

        $this->assertLessThanOrEqual(8, $locationResults->count());
        $this->assertSame('', $service->normalize('%_\\'));
    }

    private function inventoryProperty(
        int $inventory,
        bool $withRatePlan = false,
        array $typeOverrides = []
    ): array {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
            'same_day_booking' => true,
            'nightly_rate' => 100,
            'currency' => 'USD',
            'max_guests' => 4,
        ]);

        $type = AccommodationType::query()->create(array_merge([
            'property_id' => $property->id,
            'room_type_id' => $property->room_type_id,
            'name' => 'Deluxe King',
            'slug' => 'deluxe-king',
            'code' => 'TEST-'.$property->id,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'adult_capacity' => 2,
            'child_capacity' => 2,
            'max_guests' => 4,
            'total_inventory' => $inventory,
            'base_rate' => 100,
            'currency' => 'USD',
            'minimum_stay' => 1,
            'same_day_booking' => true,
            'is_active' => true,
            'is_published' => true,
        ], $typeOverrides));

        $ratePlan = null;

        if ($withRatePlan) {
            $ratePlan = RatePlan::query()->create([
                'accommodation_type_id' => $type->id,
                'name' => 'Standard Flexible',
                'code' => 'STANDARD',
                'pricing_adjustment_type' => 'none',
                'pricing_adjustment' => 0,
                'is_refundable' => true,
                'is_active' => true,
                'is_public' => true,
            ]);
        }

        return [$property, $type, $ratePlan];
    }
}
