<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\BookingCreationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingCreationRetryPayloadSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function bookingRequest(User $user, string $token, array $changes = []): Request
    {
        $payload = array_replace([
            'hold_token' => $token,
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
        ], $changes);
        $request = Request::create('/reserve', 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_identical_checkout_retry_returns_same_booking_but_changed_guest_fails(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->create([
            'is_published' => true, 'status' => 'available', 'same_day_booking' => true,
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $plan = $type->ratePlans()->first();
        $start = CarbonImmutable::today()->addDays(30);
        $hold = app(AzariAvailabilityEngine::class)->hold(
            $property, $start, $start->addDays(2), 1, 0, 1,
            $user->id, $type->id, $plan?->id
        );

        $service = app(BookingCreationService::class);
        $first = $service->create($this->bookingRequest($user, $hold->token));
        $replay = $service->create($this->bookingRequest($user, $hold->token));

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, Booking::query()->where('hold_token', $hold->token)->count());

        foreach ([
            ['guest_email' => 'different@example.test'],
            ['adults' => [['first_name' => 'Different', 'last_name' => 'Okoro']]],
            ['city' => 'Abuja'],
        ] as $tampering) {
            try {
                $service->create($this->bookingRequest($user, $hold->token, $tampering));
                $this->fail('A changed reservation payload must not reuse an idempotency key.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('hold_token', $exception->errors());
            }
        }
        $this->assertSame(1, Booking::query()->where('hold_token', $hold->token)->count());
    }

    public function test_same_key_from_different_user_cannot_reveal_booking(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $property = Property::factory()->create([
            'is_published' => true, 'status' => 'available', 'same_day_booking' => true,
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $start = CarbonImmutable::today()->addDays(30);
        $hold = app(AzariAvailabilityEngine::class)->hold(
            $property, $start, $start->addDays(2), 1, 0, 1, $owner->id, $type->id
        );

        app(BookingCreationService::class)->create($this->bookingRequest($owner, $hold->token));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BookingCreationService::class)->create($this->bookingRequest($other, $hold->token));
    }
}
