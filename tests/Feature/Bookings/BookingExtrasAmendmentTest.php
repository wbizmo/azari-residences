<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingAddOn;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingAmendmentOfferService;
use App\Services\Bookings\BookingModificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingExtrasAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $property = Property::factory()->create([
            'is_published' => true, 'status' => 'available', 'currency' => 'NGN',
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['base_rate' => 200, 'weekend_rate' => 200, 'currency' => 'NGN']);
        $guest = User::factory()->create();
        $staff = User::factory()->create(['is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff']);
        $start = CarbonImmutable::today()->addDays(28);
        $end = $start->addDays(2);
        $quote = app(AzariPricingEngine::class)->quote($property, $start, $end, [], $type, null, 1);
        $booking = Booking::factory()->for($property)->create([
            'user_id' => $guest->id, 'status' => 'confirmed',
            'accommodation_type_id' => $type->getKey(), 'rate_plan_id' => null,
            'check_in' => $start, 'check_out' => $end,
            'adults' => 2, 'children' => 0, 'rooms' => 1,
            'total' => $quote['total'], 'currency' => 'NGN',
            'voucher_id' => null, 'discount_total' => 0, 'policy_snapshot' => [],
        ]);
        Payment::query()->create([
            'reference' => 'PAY-EXTRAS-ORIGINAL', 'booking_id' => $booking->id,
            'user_id' => $guest->getKey(), 'status' => Payment::SUCCESSFUL,
            'provider' => 'manual', 'amount' => $quote['total'], 'currency' => 'NGN',
            'verified_at' => now(),
        ]);
        $addon = BookingAddOn::query()->create([
            'name' => 'Airport transfer', 'description' => 'Airport pickup',
            'pricing_type' => 'per_booking', 'price' => 50, 'is_active' => true, 'sort_order' => 0,
        ]);
        return [$booking, $guest, $staff, $addon, $start, $end];
    }

    public function test_requested_extra_is_repriced_and_added_only_after_verified_guest_acceptance(): void
    {
        [$booking, $guest, $staff, $addon, $start] = $this->fixture();
        $change = app(BookingModificationService::class)->request($booking, $guest, 'add_extras',
            ['add_on_ids' => [$addon->getKey()]]);
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking->fresh(), $change, $staff);
        $this->assertSame('quoted', $offer->status);
        $this->assertGreaterThan(0, $offer->price_quote['delta']);
        $this->assertSame(0, $booking->addOns()->count());

        $payment = Payment::query()->create([
            'booking_id' => $booking->getKey(), 'user_id' => $guest->getKey(),
            'reference' => 'PAY-EXTRAS-TOPUP', 'provider' => 'flutterwave',
            'payment_kind' => 'amendment', 'status' => 'successful_excess',
            'currency' => 'NGN', 'amount' => $offer->price_quote['delta'],
            'paid_at' => now(), 'verified_at' => now(),
        ]);
        $offer->forceFill(['payment_id' => $payment->getKey()])->save();
        $accepted = $service->accept($booking->fresh(), $offer->fresh(), $guest);
        $this->assertSame('approved', $accepted->status);
        $this->assertSame($start->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertSame(Payment::SUCCESSFUL, $payment->fresh()->status);
        $this->assertSame(1, $booking->addOns()->count());
        $this->assertSame(1, (int) $booking->addOns()->first()->pivot->quantity);
        $this->assertEqualsWithDelta(50, (float) $booking->fresh()->add_on_total, 0.01);
        $this->assertEqualsWithDelta(0, $booking->fresh()->balanceDue(), 0.01);
    }

    public function test_inactive_extra_does_not_receive_a_quote(): void
    {
        [$booking, $guest, $staff, $addon] = $this->fixture();
        $addon->forceFill(['is_active' => false])->save();
        $change = app(BookingModificationService::class)->request($booking, $guest, 'add_extras',
            ['add_on_ids' => [$addon->getKey()]]);
        $this->expectException(ValidationException::class);
        app(BookingAmendmentOfferService::class)->offer($booking, $change, $staff);
    }

    public function test_already_attached_extra_cannot_be_charged_again(): void
    {
        [$booking, $guest, $staff, $addon] = $this->fixture();
        $booking->addOns()->attach($addon, ['quantity' => 1, 'unit_price' => 50, 'line_total' => 50]);
        $change = app(BookingModificationService::class)->request($booking, $guest, 'add_extras',
            ['add_on_ids' => [$addon->getKey()]]);
        try {
            app(BookingAmendmentOfferService::class)->offer($booking, $change, $staff);
            $this->fail('Adding a purchased extra again must not silently create another charge.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('add_on_ids', $e->errors());
        }
    }
}
