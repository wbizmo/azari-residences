<?php

namespace Tests\Feature\Search;

use App\Models\AccommodationType;
use App\Models\Property;
use App\Models\RatePlan;
use App\Services\Search\MarketplaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccommodationOnlySearchRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function filters(): array
    {
        return [
            'check_in' => now()->addDays(24)->toDateString(),
            'check_out' => now()->addDays(26)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'rooms' => 1,
            'sort' => 'recommended',
        ];
    }

    public function test_a_stay_remains_searchable_when_cheapest_room_has_longer_minimum_stay(): void
    {
        $property = Property::factory()->create([
            'status' => 'available',
            'is_published' => true,
            'same_day_booking' => true,
            'latitude' => 6.5244,
            'longitude' => 3.3792,
            'minimum_stay' => 1,
        ]);

        // Factory creates a default room and plan. Cheaper room has a
        // 7-night minimum and must not hide a valid 2-night alternative.
        $primary = $property->accommodationTypes()->firstOrFail();
        $primary->update([
            'base_rate' => 15000,
            'total_inventory' => 3,
            'minimum_stay' => 7,
            'same_day_booking' => true,
            'is_active' => true,
            'is_published' => true,
        ]);
        $second = AccommodationType::query()->create([
            'property_id' => $property->id,
            'room_type_id' => $property->room_type_id,
            'name' => 'Flexible Apartment',
            'slug' => 'flexible-apartment',
            'code' => 'FLEX-'.$property->id,
            'adult_capacity' => 2,
            'child_capacity' => 0,
            'max_guests' => 2,
            'total_inventory' => 3,
            'base_rate' => 21000,
            'currency' => $property->currency,
            'minimum_stay' => 1,
            'same_day_booking' => true,
            'is_active' => true,
            'is_published' => true,
        ]);
        RatePlan::query()->create([
            'accommodation_type_id' => $second->id,
            'name' => 'Flexible rate',
            'code' => 'FLEXIBLE',
            'pricing_adjustment_type' => 'none',
            'pricing_adjustment' => 0,
            'is_active' => true,
            'is_public' => true,
            'is_refundable' => true,
        ]);

        $service = app(MarketplaceSearchService::class);
        $list = $service->search($this->filters(), false)['results'];
        $result = $list->getCollection()->first();

        $this->assertSame(1, $list->total());
        $this->assertNotNull($result);
        $this->assertSame($second->id, $result['accommodation_type']->id);
        $this->assertSame($second->id, $result['quote']['accommodation_type_id']);

        $pins = $service->mapCursor($this->filters())['points'];
        $this->assertCount(1, $pins);
        $this->assertEqualsWithDelta((float) $result['quote']['total'], $pins[0]['price'], 0.005);

        // A requested incompatible rate plan must NOT silently switch to
        // a different room or an unrelated cheaper plan.
        $badPlan = $primary->ratePlans()->first();
        if ($badPlan) {
            $scoped = $service->search([...$this->filters(), 'rate_plan_id' => $badPlan->id], false);
            $this->assertCount(0, $scoped['results']->getCollection());
        }
    }

    public function test_dynamic_facets_refresh_immediately_after_a_property_is_unpublished(): void
    {
        $property = Property::factory()->create([
            'status' => 'available',
            'is_published' => true,
            'property_type' => 'apartment',
        ]);
        $service = app(MarketplaceSearchService::class);
        $first = $service->search($this->filters());

        $this->assertSame(1, (int) ($first['facets']['property_types']['apartment'] ?? 0));

        $property->update(['is_published' => false]);
        $second = $service->search($this->filters());

        $this->assertSame(0, (int) ($second['facets']['property_types']['apartment'] ?? 0));
        $this->assertSame(0, $second['results']->total());
    }

    public function test_a_property_without_map_coordinates_can_be_searched_and_reserved(): void
    {
        $property = Property::factory()->create([
            'status' => 'available',
            'is_published' => true,
            'formatted_address' => '20 Example Road, Lagos',
            'latitude' => null,
            'longitude' => null,
            'same_day_booking' => true,
        ]);
        $filters = [...$this->filters(), 'property_id' => $property->id];
        $market = app(MarketplaceSearchService::class);

        $results = $market->search($filters, false);
        $this->assertSame(1, $results['results']->total());
        $this->assertSame($property->id,
            $results['results']->getCollection()->first()['property']->id);
        $this->assertCount(0, $market->mapCursor($filters)['points']);

        $room = $property->accommodationTypes()->firstOrFail();
        $plan = $room->ratePlans()->first();
        $hold = app(\\App\\Services\\Bookings\\AzariAvailabilityEngine::class)->hold(
            $property,
            \\Carbon\\CarbonImmutable::parse($filters['check_in']),
            \\Carbon\\CarbonImmutable::parse($filters['check_out']),
            2, 0, 1, null, $room->id, $plan?->id
        );
        $this->assertSame($property->id, $hold->property_id);
    }

    public function test_manual_address_form_exposes_optional_coordinates_without_any_geocoding_requirement(): void
    {
        $markup = file_get_contents(resource_path('views/components/property-address-fields.blade.php'));

        $this->assertStringContainsString('name="formatted_address"', $markup);
        $this->assertStringContainsString('name="latitude"', $markup);
        $this->assertStringContainsString('name="longitude"', $markup);
        $this->assertStringContainsString('<details', $markup);
        $this->assertStringContainsString('Address suggestions (optional)', $markup);
        $this->assertStringNotContainsString('style="display:none"', $markup);
    }
}
