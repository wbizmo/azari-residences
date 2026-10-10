<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\AccommodationType;
use App\Models\MaintenancePeriod;
use App\Models\Property;
use App\Services\Search\PropertyStayPriceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyStayPriceCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function propertyWithRoom(): array
    {
        $property = Property::factory()->create([
            'status' => 'active',
            'is_published' => true,
            'timezone' => 'Africa/Lagos',
            'same_day_booking' => true,
            'cleaning_fee' => 1200,
            'tax_rate' => 7.5,
        ]);
        $type = AccommodationType::query()->create([
            'property_id' => $property->id,
            'name' => 'Monthly quote guest room',
            'slug' => 'monthly-quote-room',
            'code' => 'MON1',
            'adult_capacity' => 2,
            'child_capacity' => 0,
            'max_guests' => 2,
            'total_inventory' => 3,
            'base_rate' => 10000,
            'currency' => 'NGN',
            'is_active' => true,
            'is_published' => true,
        ]);
        return [$property, $type];
    }

    public function test_month_has_true_canonical_full_stay_totals_and_preserves_selected_room(): void
    {
        CarbonImmutable::setTestNow('2026-10-10 10:00:00 Africa/Lagos');
        try {
            [$property, $type] = $this->propertyWithRoom();
            $result = app(PropertyStayPriceCalendar::class)->month($property, [
                'month' => '2026-10', 'nights' => 2, 'adults' => 2,
                'children' => 0, 'rooms' => 1,
                'accommodation_type_id' => $type->id,
            ]);

            $this->assertCount(31, $result['days']);
            $this->assertFalse($result['days'][0]['available']);
            $date = collect($result['days'])->firstWhere('date', '2026-10-14');
            $this->assertTrue($date['available']);
            $this->assertSame('2026-10-16', $date['check_out']);
            $this->assertSame('NGN', $date['currency']);
            $this->assertGreaterThan(20000, $date['total']);
            $this->assertStringContainsString('accommodation_type_id='.$type->id, $date['url']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_maintenance_suppresses_only_overlapping_arrival_stays(): void
    {
        CarbonImmutable::setTestNow('2026-10-10 10:00:00 Africa/Lagos');
        try {
            [$property, $type] = $this->propertyWithRoom();
            MaintenancePeriod::query()->create([
                'property_id' => $property->id,
                'starts_on' => '2026-10-20',
                'ends_on' => '2026-10-22',
                'title' => 'Planned maintenance',
                'blocks_booking' => true,
            ]);
            $result = app(PropertyStayPriceCalendar::class)->month($property, [
                'month' => '2026-10', 'nights' => 2, 'adults' => 2,
                'children' => 0, 'rooms' => 1, 'accommodation_type_id' => $type->id,
            ]);
            $days = collect($result['days'])->keyBy('date');
            $this->assertFalse($days['2026-10-19']['available']);
            $this->assertFalse($days['2026-10-20']['available']);
            $this->assertTrue($days['2026-10-22']['available']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_controller_rejects_unpublished_or_cross_property_rates_and_long_probe(): void
    {
        [$property, $type] = $this->propertyWithRoom();
        $other = $this->propertyWithRoom()[1];
        $url = route('properties.price-calendar', $property);

        $this->get($url.'?nights=366')->assertSessionHasErrors('nights');
        $this->get($url.'?accommodation_type_id='.$other->id)
            ->assertSessionHasErrors('accommodation_type_id');
        $property->update(['is_published' => false]);
        $this->get($url)->assertNotFound();
    }
}
