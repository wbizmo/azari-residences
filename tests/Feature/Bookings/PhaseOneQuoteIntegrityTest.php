<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use App\Models\User;
use App\Models\Booking;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingCreationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    public function test_checkout_rejects_changed_rate_instead_of_silently_charging_a_new_amount(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->create([
            'is_published' => true,
            'status' => 'available',
            'same_day_booking' => true,
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $plan = $type->ratePlans()->first();
        $start = CarbonImmutable::today()->addDays(40);
        $hold = app(AzariAvailabilityEngine::class)->hold(
            $property, $start, $start->addDays(2), 1, 0, 1,
            $user->id, $type->id, $plan?->id
        );
        $type->update(['base_rate' => 1000, 'weekend_rate' => 1000]);

        $request = Request::create('/reserve', 'POST', [
            'hold_token' => $hold->token,
            'first_name' => 'Ada',
            'last_name' => 'Okoro',
            'guest_email' => 'ada@example.test',
            'guest_phone' => '+2348000000000',
            'nationality' => 'Nigerian',
            'address' => '1 Test Street',
            'city' => 'Lagos',
            'country' => 'Nigeria',
            'adults' => [['first_name' => 'Ada', 'last_name' => 'Okoro']],
            'children' => [],
            'terms' => '1',
        ]);
        $request->setUserResolver(fn () => $user);

        try {
            app(BookingCreationService::class)->create($request);
            $this->fail('Changed prices must not silently proceed to a booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('hold_token', $exception->errors());
        }

        $this->assertSame(0, Booking::query()->where('hold_token', $hold->token)->count());
    }
}
