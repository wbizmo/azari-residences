<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\MaintenancePeriod;
use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\OwnerInventoryCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAvailabilityCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function propertyWithRoom(): array
    {
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'active',
        ]);
        $type = AccommodationType::query()->create([
            'property_id' => $property->id,
            'name' => 'Guest rooms',
            'slug' => 'guest-rooms',
            'code' => 'CAL1',
            'adult_capacity' => 2,
            'child_capacity' => 0,
            'max_guests' => 2,
            'total_inventory' => 4,
            'base_rate' => 15000,
            'currency' => 'NGN',
            'is_active' => true,
            'is_published' => true,
        ]);

        return [$property, $type];
    }

    public function test_maintenance_only_blocks_overlapping_dates_not_whole_calendar_window(): void
    {
        [$property, $room] = $this->propertyWithRoom();
        $start = CarbonImmutable::today()->addDays(10);
        MaintenancePeriod::query()->create([
            'property_id' => $property->id,
            'starts_on' => $start->addDay()->toDateString(),
            'ends_on' => $start->addDays(2)->toDateString(),
            'title' => 'Single night water service',
            'blocks_booking' => true,
        ]);

        $remaining = app(AzariAvailabilityEngine::class)->remainingByDate(
            $room, $start, $start->addDays(3)
        );

        $this->assertSame(4, $remaining->get($start->toDateString()));
        $this->assertSame(0, $remaining->get($start->addDay()->toDateString()));
        $this->assertSame(4, $remaining->get($start->addDays(2)->toDateString()));
    }

    public function test_owner_week_and_month_boards_use_canonical_occupancy_and_night_counts(): void
    {
        [$property, $room] = $this->propertyWithRoom();
        $start = CarbonImmutable::today()->addDays(10);
        Booking::factory()->create([
            'property_id' => $property->id,
            'accommodation_type_id' => $room->id,
            'check_in' => $start->toDateString(),
            'check_out' => $start->addDay()->toDateString(),
            'rooms' => 2,
            'status' => 'confirmed',
        ]);

        $calendar = app(OwnerInventoryCalendarService::class);
        $week = $calendar->forProperty($property, $start, 'week');
        $weekType = collect($week['types'])->first(fn (array $row) => (int) $row['type']->id === (int) $room->id);
        $cell = $weekType['dates'][$start->toDateString()];

        $this->assertSame('week', $week['view']);
        $this->assertCount(7, $week['weeks'][0]);
        $this->assertSame(2, $cell['booked']);
        $this->assertSame(2, $cell['remaining']);
        $this->assertSame(0, $cell['external']);

        $month = $calendar->forProperty($property, $start, 'month');
        $this->assertGreaterThanOrEqual(4, count($month['weeks']));
        $this->assertLessThanOrEqual(6, count($month['weeks']));
        $this->assertSame(2, collect($month['types'])->first(fn (array $row) => (int) $row['type']->id === (int) $room->id)['dates'][$start->toDateString()]['remaining']);
    }
}
