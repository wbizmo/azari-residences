<?php

namespace Tests\Feature\Bookings;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Property;
use App\Services\Bookings\AvailabilityService;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\InventoryBulkUpdateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseOneInventorySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_missing_or_foreign_accommodation_cannot_fall_back_to_property_availability(): void
    {
        $first = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $second = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $foreignType = $second->accommodationTypes()->firstOrFail();
        $start = CarbonImmutable::today()->addDays(20);
        $end = $start->addDays(2);
        $engine = app(AzariAvailabilityEngine::class);

        $this->assertFalse($engine->availableForProperty($first, $start, $end, 1, $foreignType->id));
        $this->assertFalse($engine->availableForProperty($first, $start, $end, 1, 999999));
    }

    public function test_legacy_availability_check_uses_same_quantity_engine_as_search_and_holds(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['total_inventory' => 1]);
        $start = CarbonImmutable::today()->addDays(23);
        $end = $start->addDays(2);

        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $type->id,
            'status' => 'confirmed',
            'check_in' => $start,
            'check_out' => $end,
            'rooms' => 1,
        ]);

        $this->assertSame(0, app(AzariAvailabilityEngine::class)->availableQuantity($type->fresh(), $start, $end));
        $this->assertFalse(app(AvailabilityService::class)->isAvailable($property->id, $start, $end));
    }

    public function test_bulk_inventory_cannot_cut_capacity_below_existing_confirmed_booking(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['total_inventory' => 2]);
        $start = CarbonImmutable::today()->addDays(27);
        $end = $start->addDay();

        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $type->id,
            'status' => 'confirmed',
            'check_in' => $start,
            'check_out' => $end,
            'rooms' => 2,
        ]);

        $this->expectException(ValidationException::class);
        app(InventoryBulkUpdateService::class)->apply($type->fresh(), $start, $start, ['sellable_inventory' => 1], null);
    }

    public function test_bulk_inventory_can_reduce_unused_capacity_without_touching_confirmed_stays(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['total_inventory' => 3]);
        $start = CarbonImmutable::today()->addDays(30);

        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $type->id,
            'status' => 'confirmed',
            'check_in' => $start,
            'check_out' => $start->addDay(),
            'rooms' => 1,
        ]);

        app(InventoryBulkUpdateService::class)->apply($type->fresh(), $start, $start, ['sellable_inventory' => 1], null);
        $this->assertSame(0, app(AzariAvailabilityEngine::class)->availableQuantity($type->fresh(), $start, $start->addDay()));
    }

    public function test_unbounded_stays_are_rejected_before_inventory_range_materialization(): void
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $type = $property->accommodationTypes()->firstOrFail();
        $start = CarbonImmutable::today()->addDays(10);
        $this->expectException(ValidationException::class);

        app(AzariAvailabilityEngine::class)->assertRules($property, $start, $start->addDays(367), 1, 0, 1, $type);
    }
}
