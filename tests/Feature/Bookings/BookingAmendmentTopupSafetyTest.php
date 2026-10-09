<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingAmendmentOfferService;
use App\Services\Bookings\BookingModificationService;
use App\Services\Bookings\ExpiredAmendmentRecoveryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingAmendmentTopupSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function prepared(): array
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
        $quoted = app(AzariPricingEngine::class)->quote($property, $newDate, $newDate->addDays(2), [], $type, null, 1);
        $booking = Booking::factory()->for($property)->create([
            'user_id' => $guest->getKey(), 'status' => 'confirmed',
            'accommodation_type_id' => $type->getKey(), 'rate_plan_id' => null,
            'check_in' => $oldDate, 'check_out' => $oldDate->addDays(2),
            'rooms' => 1, 'adults' => 2, 'children' => 0,
            'currency' => $quoted['currency'], 'total' => round((float) $quoted['total'] - 50, 2),
            'voucher_id' => null, 'discount_total' => 0,
            'paid_at' => null, 'receipt_number' => null, 'payment_reference' => null,
        ]);
        $original = Payment::query()->create([
            'booking_id' => $booking->getKey(), 'user_id' => $guest->getKey(),
            'provider' => 'manual', 'reference' => 'PAY-ORIGINAL-TOPUP-1',
            'amount' => (float) $booking->total, 'currency' => $booking->currency,
            'status' => Payment::SUCCESSFUL, 'payment_kind' => 'full', 'verified_at' => now(),
        ]);
        $request = app(BookingModificationService::class)->request(
            $booking, $guest, 'date_change',
            ['check_in' => $newDate->toDateString(), 'check_out' => $newDate->addDays(2)->toDateString()]
        );
        $offer = app(BookingAmendmentOfferService::class)->offer($booking, $request, $staff);
        $topup = Payment::query()->create([
            'booking_id' => $booking->getKey(), 'user_id' => $guest->getKey(),
            'provider' => 'flutterwave', 'reference' => 'PAY-TOPUP-SAFE-1',
            'provider_reference' => 'chg-topup-safe-1', 'status' => 'successful_excess',
            'payment_kind' => 'amendment', 'amount' => round((float) $offer->price_quote['delta'], 2),
            'currency' => $booking->currency, 'verified_at' => now(), 'paid_at' => now(),
        ]);
        $offer->forceFill(['payment_id' => $topup->getKey()])->save();
        return [$booking, $guest, $offer, $topup, $newDate, $oldDate, $original];
    }

    public function test_verified_unallocated_payment_allocates_once_only_after_guest_acceptance(): void
    {
        [$booking, $guest, $offer, $topup, $newDate] = $this->prepared();
        $this->assertSame('successful_excess', $topup->status);
        $this->assertSame(0, $topup->refunds()->count());
        $accepted = app(BookingAmendmentOfferService::class)->accept($booking, $offer, $guest);
        $this->assertSame('approved', $accepted->status);
        $this->assertSame($newDate->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertSame(Payment::SUCCESSFUL, $topup->fresh()->status);
        $this->assertEqualsWithDelta((float) $booking->fresh()->total, (float) $booking->fresh()->successfulPaymentsTotal(), 0.01);

        $this->expectException(ValidationException::class);
        app(BookingAmendmentOfferService::class)->accept($booking->fresh(), $accepted, $guest);
    }

    public function test_expired_quote_recovers_verified_topup_as_refund_without_moving_existing_booking(): void
    {
        [$booking, $guest, $offer, $topup, $newDate, $oldDate] = $this->prepared();
        $offer->forceFill(['quote_expires_at' => now()->subMinute()])->save();
        $service = app(ExpiredAmendmentRecoveryService::class);
        $refund = $service->requestRecovery($offer);
        $this->assertNotNull($refund);
        $this->assertSame('requested', $refund->status);
        $this->assertSame((float) $topup->amount, (float) $refund->amount);
        $this->assertSame('refund_pending', $offer->fresh()->status);
        $this->assertSame('successful_excess', $topup->fresh()->status);
        $this->assertSame($oldDate->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertNull($service->requestRecovery($offer->fresh()));
        $this->assertSame(1, $topup->refunds()->count());
    }

    public function test_reserved_refund_cannot_be_reused_to_confirm_new_dates(): void
    {
        [$booking, $guest, $offer, $topup] = $this->prepared();
        app(\App\Services\Payments\RefundService::class)->request(
            $topup, (float) $topup->amount, $guest->getKey(), 'Cancellation recovery',
            'amendment-refund-unique-safety'
        );
        try {
            app(BookingAmendmentOfferService::class)->accept($booking, $offer, $guest);
            $this->fail('A top-up already committed to refund must not fund amended inventory.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
    }
}
