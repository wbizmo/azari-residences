<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\DailyRate;
use App\Models\InventoryDate;
use App\Models\Property;
use App\Services\Bookings\CommercialInventoryManager;
use App\Services\Bookings\InventoryBulkUpdateService;
use App\Services\PhaseThree\OwnerYieldAdvisor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BatchThreeInventoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function propertyWithUnits(int $capacity = 4): array
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['total_inventory' => $capacity, 'base_rate' => 45000,
            'is_active' => true, 'is_published' => true]);
        return [$property, $type];
    }

    private function editUnits(Property $property, $type, int $capacity): void
    {
        app(CommercialInventoryManager::class)->saveAccommodationType($property, [
            'name' => $type->name,
            'slug' => $type->slug,
            'adult_capacity' => $type->adult_capacity,
            'child_capacity' => $type->child_capacity,
            'max_guests' => $type->max_guests,
            'total_inventory' => $capacity,
            'base_rate' => $type->base_rate,
        ], $type);
    }

    public function test_capacity_reduction_refuses_confirmed_multiple_room_nights_without_mutation(): void
    {
        [$property, $type] = $this->propertyWithUnits(4);
        $day = CarbonImmutable::today()->addDays(40);
        $booking = Booking::factory()->create([
            'property_id' => $property->id, 'accommodation_type_id' => $type->id,
            'check_in' => $day, 'check_out' => $day->addDays(2),
            'rooms' => 3, 'status' => 'confirmed', 'total' => 189000,
        ]);
        try {
            $this->editUnits($property, $type, 2);
            $this->fail('Capacity reduction below confirmed rooms was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('total_inventory', $exception->errors());
        }
        $this->assertSame(4, (int) $type->fresh()->total_inventory);
        $this->assertSame(189000.0, (float) $booking->fresh()->total);
        $this->assertTrue((bool) $property->fresh()->is_published);
    }

    public function test_active_holds_and_calendar_allotments_cannot_be_displaced_by_type_edits(): void
    {
        [$property, $type] = $this->propertyWithUnits(4);
        $day = CarbonImmutable::today()->addDays(50);
        $hold = BookingHold::query()->create([
            'property_id' => $property->id, 'accommodation_type_id' => $type->id,
            'check_in' => $day, 'check_out' => $day->addDay(),
            'adults' => 2, 'children' => 0, 'rooms' => 3,
            'expires_at' => now()->addMinutes(15),
        ]);
        try {
            $this->editUnits($property, $type, 2);
            $this->fail('Active hold capacity was overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('total_inventory', $exception->errors());
        }

        $hold->update(['expires_at' => now()->subMinute()]);
        InventoryDate::query()->create([
            'accommodation_type_id' => $type->id,
            'date' => $day->toDateString(), 'sellable_inventory' => 4,
        ]);
        try {
            $this->editUnits($property, $type, 3);
            $this->fail('Explicit calendar allotment exceeded new room count.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('total_inventory', $exception->errors());
        }
        $this->assertSame(4, (int) $type->fresh()->total_inventory);
    }

    public function test_safe_reduction_with_no_active_conflicts_succeeds(): void
    {
        [$property, $type] = $this->propertyWithUnits(4);
        $this->editUnits($property, $type, 2);
        $this->assertSame(2, (int) $type->fresh()->total_inventory);
    }

    public function test_stale_inventory_preview_cannot_override_new_base_rate_or_daily_rate(): void
    {
        [$property, $type] = $this->propertyWithUnits(4);
        $day = CarbonImmutable::today()->addDays(42);
        $calendar = app(InventoryBulkUpdateService::class);
        $preview = $calendar->preview($type, $day, $day, ['price_override' => '62000.00']);
        $type->update(['base_rate' => 49000]);

        try {
            $calendar->apply($type, $day, $day, ['price_override' => '62000.00'],
                null, 'admin', $preview['revision']);
            $this->fail('Rate edit did not invalidate the previous preview.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_revision', $exception->errors());
        }

        $preview2 = $calendar->preview($type->fresh(), $day, $day, ['price_override' => '62000.00']);
        DailyRate::query()->create([
            'accommodation_type_id' => $type->id,
            'date' => $day->toDateString(), 'amount' => 57000,
        ]);
        try {
            $calendar->apply($type->fresh(), $day, $day, ['price_override' => '62000.00'],
                null, 'admin', $preview2['revision']);
            $this->fail('Daily price change did not invalidate the previous preview.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_revision', $exception->errors());
        }
    }

    public function test_yield_advice_preserves_owner_manual_override_and_closed_inventory(): void
    {
        [$property, $type] = $this->propertyWithUnits(8);
        $from = CarbonImmutable::today()->addDays(70);
        $to = $from->addDays(6);
        for ($i = 0; $i < 5; $i++) {
            Booking::factory()->create([
                'property_id' => $property->id, 'accommodation_type_id' => $type->id,
                'check_in' => $from->addDays($i),
                'check_out' => $from->addDays($i + 1),
                'rooms' => 1, 'status' => 'confirmed',
            ]);
        }

        $advisor = app(OwnerYieldAdvisor::class);
        $first = $advisor->preview($type->fresh(), $from, $to);
        $this->assertTrue($first['eligible']);
        $this->assertNotNull($first['suggested_rate']);
        $this->assertFalse($first['applied']);

        InventoryDate::query()->create([
            'accommodation_type_id' => $type->id,
            'date' => $from->addDays(3)->toDateString(),
            'price_override' => 36000,
        ]);
        $manual = $advisor->preview($type->fresh(), $from, $to);
        $this->assertFalse($manual['eligible']);
        $this->assertNull($manual['suggested_rate']);

        InventoryDate::query()->where('accommodation_type_id', $type->id)->delete();
        InventoryDate::query()->create([
            'accommodation_type_id' => $type->id, 'date' => $from->toDateString(),
            'closed_to_arrival' => true,
        ]);
        $closed = $advisor->preview($type->fresh(), $from, $to);
        $this->assertFalse($closed['eligible']);
    }

    public function test_leap_day_calendar_window_has_correct_inclusive_nights_and_revision(): void
    {
        [$property, $type] = $this->propertyWithUnits(4);
        $from = CarbonImmutable::parse('2028-02-28');
        $to = CarbonImmutable::parse('2028-03-01');
        $preview = app(InventoryBulkUpdateService::class)->preview(
            $type, $from, $to, ['stop_sell' => true]
        );
        $this->assertSame(3, $preview['days']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $preview['revision']);
        $this->assertSame(0, $preview['affected_committed_dates']);
    }
}
