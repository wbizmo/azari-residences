<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneQuoteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_holds_store_authoritative_quoted_total_currency_and_rate(): void
    {
        $property = Property::factory()->create([
            'status' => 'available',
            'is_published' => true,
            'same_day_booking' => true,
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $rate = $type->ratePlans()->first();
        $start = CarbonImmutable::today()->addDays(40);
        $end = $start->addDays(2);
        $hold = app(AzariAvailabilityEngine::class)->hold(
            $property, $start, $end, 1, 0, 1, null,
            $type->getKey(), $rate?->getKey()
        );
        $calculated = app(AzariPricingEngine::class)->quote(
            $property, $start, $end, [], $type, $rate, 1
        );

        $this->assertSame(1, $hold->pricing_snapshot['version']);
        $this->assertSame($calculated['currency'], $hold->pricing_snapshot['currency']);
        $this->assertEqualsWithDelta((float) $calculated['total'], (float) $hold->pricing_snapshot['total'], 0.001);
        $this->assertSame(1, $hold->pricing_snapshot['quantity']);
    }
}
