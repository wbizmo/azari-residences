<?php

namespace Tests\Feature\Bookings;

use App\Contracts\Payments\PaymentProvider;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingAmendmentOfferService;
use App\Services\Bookings\BookingAmendmentPaymentService;
use App\Services\Bookings\BookingModificationService;
use App\Services\Payments\PaymentFinalizer;
use App\Services\Payments\PaymentManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class BookingAmendmentTopupTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available']);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['total_inventory' => 1, 'base_rate' => 200, 'weekend_rate' => 200]);
        $guest = User::factory()->create();
        $staff = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        $oldDate = CarbonImmutable::today()->addDays(24);
        $newDate = $oldDate->addDays(7);
        $newQuote = app(AzariPricingEngine::class)->quote($property, $newDate, $newDate->addDays(2), [], $type, null, 1);
        $oldTotal = round((float) $newQuote['total'] - 50, 2);
        $booking = Booking::factory()->for($property)->create([
            'user_id' => $guest->id, 'status' => 'confirmed',
            'accommodation_type_id' => $type->getKey(), 'rate_plan_id' => null,
            'check_in' => $oldDate, 'check_out' => $oldDate->addDays(2),
            'rooms' => 1, 'adults' => 2, 'children' => 0,
            'total' => $oldTotal, 'currency' => $newQuote['currency'],
            'voucher_id' => null, 'discount_total' => 0,
        ]);
        Payment::query()->create([
            'booking_id' => $booking->getKey(), 'user_id' => $guest->getKey(),
            'provider' => 'manual', 'reference' => 'PAY-ORIGINAL-BOOKING',
            'status' => Payment::SUCCESSFUL, 'payment_kind' => 'full',
            'currency' => $newQuote['currency'], 'amount' => $oldTotal, 'verified_at' => now(),
        ]);
        $request = app(BookingModificationService::class)->request($booking, $guest, 'date_change', [
            'check_in' => $newDate->toDateString(),
            'check_out' => $newDate->addDays(2)->toDateString(),
        ]);
        $offer = app(BookingAmendmentOfferService::class)->offer($booking->fresh(), $request, $staff);
        $this->assertGreaterThan(0, $offer->price_quote['delta']);
        return [$booking, $offer, $guest, $staff, $newDate, $oldDate, $property, $type];
    }

    private function mockCheckout(): void
    {
        $driver = Mockery::mock(PaymentProvider::class);
        $driver->shouldReceive('enabled')->andReturn(true);
        $driver->shouldReceive('initialise')->once()->andReturn([
            'checkout_url' => 'https://example.test/secure-amendment-checkout',
            'provider_reference' => 'chg-amendment-123',
            'safe_response' => ['status' => 'pending'],
        ]);
        $manager = Mockery::mock(PaymentManager::class);
        $manager->shouldReceive('driver')->with('flutterwave')->andReturn($driver);
        app()->instance(PaymentManager::class, $manager);
    }

    public function test_verified_topup_is_unallocated_until_guest_accepts_and_then_balances_booking(): void
    {
        [$booking, $offer, $guest, $staff, $newDate] = $this->fixture();
        $this->mockCheckout();
        $service = app(BookingAmendmentPaymentService::class);
        $payment = $service->initiate($booking, $offer, $guest);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('amendment', $payment->payment_kind);
        $this->assertEqualsWithDelta(50, (float) $payment->amount, 0.01);
        $this->assertSame($payment->getKey(), $service->initiate($booking, $offer->fresh(), $guest)->getKey());

        $finalized = app(PaymentFinalizer::class)->apply($payment, [
            'merchant_reference' => $payment->reference,
            'provider_reference' => 'chg-amendment-123',
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => 'successful',
            'provider_status' => 'succeeded',
        ], 'test');
        $this->assertSame('successful_excess', $finalized->status);
        $this->assertEqualsWithDelta((float) $offer->price_quote['old_total'], (float) $booking->fresh()->total, 0.01);

        $applied = app(BookingAmendmentOfferService::class)->accept($booking->fresh(), $offer->fresh(), $guest);
        $this->assertSame('approved', $applied->status);
        $this->assertSame($newDate->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertSame(Payment::SUCCESSFUL, $payment->fresh()->status);
        $this->assertEqualsWithDelta(0, $booking->fresh()->balanceDue(), 0.01);
        $this->assertSame(Payment::SUCCESSFUL, app(PaymentFinalizer::class)->apply($payment->fresh(), [
            'merchant_reference' => $payment->reference,
            'amount' => (float) $payment->amount, 'currency' => $payment->currency,
            'status' => 'successful', 'provider_status' => 'succeeded',
        ])->status);
    }

    public function test_after_external_topup_is_paid_a_lost_room_leaves_original_booking_and_funds_unallocated(): void
    {
        [$booking, $offer, $guest, $staff, $newDate, $oldDate, $property, $type] = $this->fixture();
        $this->mockCheckout();
        $payment = app(BookingAmendmentPaymentService::class)->initiate($booking, $offer, $guest);
        app(PaymentFinalizer::class)->apply($payment, [
            'merchant_reference' => $payment->reference,
            'amount' => (float) $payment->amount, 'currency' => $payment->currency,
            'status' => 'successful', 'provider_status' => 'succeeded',
        ]);
        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $type->getKey(), 'status' => 'confirmed',
            'check_in' => $newDate, 'check_out' => $newDate->addDays(2), 'rooms' => 1,
        ]);

        try {
            app(BookingAmendmentOfferService::class)->accept($booking->fresh(), $offer->fresh(), $guest);
            $this->fail('Inventory may not be oversold to accept a previously paid amendment.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('change', $error->errors());
        }
        $this->assertSame($oldDate->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertSame('successful_excess', $payment->fresh()->status);
    }

    public function test_stranger_cannot_start_payment_for_another_customer_amendment(): void
    {
        [$booking, $offer] = $this->fixture();
        $stranger = User::factory()->create();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BookingAmendmentPaymentService::class)->initiate($booking, $offer, $stranger);
    }
}
