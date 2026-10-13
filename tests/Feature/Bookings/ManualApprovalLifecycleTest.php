<?php

namespace Tests\Feature\Bookings;

use App\Models\AccommodationBookingApproval;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\BookingCreationService;
use App\Services\Bookings\AccommodationBookingApprovalService;
use App\Services\Payments\PaymentEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ManualApprovalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function submitRequest(?User $user = null, ?Property $property = null): Booking
    {
        $user ??= User::factory()->create();
        $property ??= Property::factory()->create([
            'status' => 'available', 'is_published' => true,
            'same_day_booking' => true,
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $plan = $type->ratePlans()->first();
        $start = CarbonImmutable::today()->addDays(20);
        $hold = app(AzariAvailabilityEngine::class)->hold(
            $property, $start, $start->addDays(2),
            1, 0, 1, $user->id, $type->id, $plan?->id
        );

        $request = Request::create('/booking', 'POST', [
            'hold_token' => $hold->token,
            'first_name' => 'Ada', 'last_name' => 'Okoro',
            'guest_email' => 'ada@example.test',
            'guest_phone' => '+2348000000000',
            'nationality' => 'Nigerian', 'address' => '14 Example Way',
            'city' => 'Lagos', 'country' => 'Nigeria',
            'adults' => [['first_name' => 'Ada', 'last_name' => 'Okoro']],
            'children' => [], 'terms' => '1',
        ]);
        $request->setUserResolver(fn () => $user);

        return app(BookingCreationService::class)->create($request);
    }

    public function test_new_booking_is_an_unpaid_request_and_payment_is_blocked_until_approved(): void
    {
        config()->set('reserva.bookings.require_approval', true);
        $booking = $this->submitRequest();

        $this->assertSame('pending', $booking->status);
        $this->assertSame('submitted', $booking->approval?->status);
        $this->assertFalse($booking->canAcceptPayment());
        $this->assertFalse(app(PaymentEligibilityService::class)->acceptsPayment($booking));
        $this->assertSame(0, $booking->payments()->count());
    }

    public function test_authorized_owner_approval_is_idempotent_and_enables_payment_without_confirming(): void
    {
        config()->set('reserva.bookings.require_approval', true);
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id, 'status' => 'available',
            'is_published' => true, 'same_day_booking' => true,
        ]);
        $booking = $this->submitRequest(null, $property);
        $service = app(AccommodationBookingApprovalService::class);

        $approved = $service->approve($booking, $owner, 'Available for your stay.');
        $this->assertSame('pending_payment', $approved->status);
        $this->assertSame('approved', $approved->approval?->status);
        $this->assertTrue($approved->canAcceptPayment());
        $this->assertSame(0, $approved->payments()->count());

        $service->approve($approved, $owner, 'Available for your stay.');
        $this->assertSame(1, $booking->statusHistory()->where('to_status', 'pending_payment')->count());
    }

    public function test_unrelated_owner_cannot_review_or_read_another_property_request(): void
    {
        config()->set('reserva.bookings.require_approval', true);
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id, 'status' => 'available',
            'is_published' => true, 'same_day_booking' => true,
        ]);
        $booking = $this->submitRequest(null, $property);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('user.owner.booking-requests.approve', $booking), ['note' => 'ok'])
            ->assertNotFound();

        $this->assertSame('submitted', $booking->approval()->firstOrFail()->status);
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_decline_has_a_reason_and_cannot_be_reapproved(): void
    {
        config()->set('reserva.bookings.require_approval', true);
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id, 'status' => 'available',
            'is_published' => true, 'same_day_booking' => true,
        ]);
        $booking = $this->submitRequest(null, $property);
        $service = app(AccommodationBookingApprovalService::class);

        $declined = $service->decline($booking, $owner, 'The accommodation is undergoing repairs.');
        $this->assertSame('cancelled', $declined->status);
        $this->assertSame('declined', $declined->approval?->status);
        $this->assertFalse($declined->canAcceptPayment());

        $this->expectException(ValidationException::class);
        $service->approve($declined, $owner);
    }

    public function test_approval_cannot_accept_expired_requests_or_repriced_inventory(): void
    {
        config()->set('reserva.bookings.require_approval', true);
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id, 'status' => 'available',
            'is_published' => true, 'same_day_booking' => true,
        ]);
        $booking = $this->submitRequest(null, $property);
        $type = $booking->accommodationType;
        $type->update(['base_rate' => (float) $type->base_rate + 500]);

        try {
            app(AccommodationBookingApprovalService::class)->approve($booking, $owner);
            $this->fail('Stale quote must never be silently approved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }

        $booking->update(['expires_at' => now()->subMinute()]);
        $this->expectException(ValidationException::class);
        app(AccommodationBookingApprovalService::class)->approve($booking, $owner);
    }

    public function test_legacy_instant_bookings_preserve_payment_eligibility(): void
    {
        config()->set('reserva.bookings.require_approval', false);
        $legacy = $this->submitRequest();
        $this->assertSame('pending_payment', $legacy->status);
        $this->assertNull($legacy->approval);
        $this->assertTrue(app(PaymentEligibilityService::class)->acceptsPayment($legacy));
    }
}
