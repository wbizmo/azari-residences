<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\InventoryBulkUpdateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefocusInventoryBookingConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_or_closed_property_cannot_be_held_even_with_spare_rooms(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $start = CarbonImmutable::today()->addDays(31);
        $end = $start->addDays(2);
        $engine = app(AzariAvailabilityEngine::class);

        $property->update(['status' => 'maintenance']);
        $this->assertFalse($engine->availableForProperty($property->fresh(), $start, $end, 1, $type->id));
        try {
            $engine->hold($property, $start, $end, 1, 0, 1, null, $type->id);
            $this->fail('A closed property must not acquire a booking hold.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('property_id', $exception->errors());
        }

        $property->update(['status' => 'available', 'is_published' => false]);
        $this->assertFalse($engine->availableForProperty($property->fresh(), $start, $end, 1, $type->id));
        $this->expectException(ValidationException::class);
        $engine->hold($property, $start, $end, 1, 0, 1, null, $type->id);
    }

    public function test_room_type_with_zero_child_capacity_rejects_children(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['adult_capacity' => 4, 'child_capacity' => 0, 'max_guests' => 4]);
        $checkIn = CarbonImmutable::today()->addDays(35);

        try {
            app(AzariAvailabilityEngine::class)->assertRules(
                $property, $checkIn, $checkIn->addDays(2), 1, 1, 1, $type->fresh()
            );
            $this->fail('Zero child capacity must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('children', $exception->errors());
        }
    }

    public function test_inventory_service_rejects_invalid_minimum_stay_even_outside_http_form(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $day = CarbonImmutable::today()->addDays(42);

        $this->expectException(ValidationException::class);
        app(InventoryBulkUpdateService::class)->apply(
            $type, $day, $day, ['minimum_stay' => 0], null
        );
    }

    public function test_inventory_service_refuses_price_overrides_beyond_currency_minor_units(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $day = CarbonImmutable::today()->addDays(44);

        $this->expectException(ValidationException::class);
        app(InventoryBulkUpdateService::class)->apply(
            $type, $day, $day, ['price_override' => '89.999'], null
        );
    }

    public function test_calendar_bulk_edit_refuses_effective_maximum_below_existing_minimum(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['minimum_stay' => 3]);
        $day = CarbonImmutable::today()->addDays(46);

        $this->expectException(ValidationException::class);
        app(InventoryBulkUpdateService::class)->apply(
            $type, $day, $day, ['maximum_stay' => 2], null
        );
    }
}
