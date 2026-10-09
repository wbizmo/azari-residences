<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicQuoteSelectionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function inputs(): array
    {
        $in = CarbonImmutable::today()->addDays(30);
        return [
            'check_in' => $in->toDateString(),
            'check_out' => $in->addDays(2)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'rooms' => 1,
        ];
    }

    public function test_public_quote_rejects_accommodation_type_belonging_to_another_property(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $other = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $foreignType = $other->accommodationTypes()->firstOrFail();

        $this->postJson(route('azari.availability.quote', $property), [
            ...$this->inputs(),
            'accommodation_type_id' => $foreignType->getKey(),
        ])->assertUnprocessable()->assertJsonValidationErrors('accommodation_type_id');
    }

    public function test_public_quote_rejects_rate_plan_that_does_not_belong_to_selected_type(): void
    {
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $other = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $foreignPlan = $other->accommodationTypes()->firstOrFail()->ratePlans()->firstOrFail();

        $this->postJson(route('azari.availability.quote', $property), [
            ...$this->inputs(),
            'accommodation_type_id' => $type->getKey(),
            'rate_plan_id' => $foreignPlan->getKey(),
        ])->assertUnprocessable()->assertJsonValidationErrors('rate_plan_id');
    }
}
