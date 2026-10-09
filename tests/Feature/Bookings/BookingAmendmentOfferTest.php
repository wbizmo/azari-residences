<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingModificationRequest;
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

class BookingAmendmentOfferTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $property = Property::factory()->create([
            'is_published' => true, 'status' => 'available',
        ]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['total_inventory' => 1, 'base_rate' => 200, 'weekend_rate' => 200]);
        $guest = User::factory()->create();
        $staff = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        $original = CarbonImmutable::today()->addDays(24);
        $new = $original->addDays(7);

        $booking = Booking::factory()->for($property)->create([
            'user_id' => $guest->id,
            'status' => 'confirmed',
            'accommodation_type_id' => $type->id,
            'rate_plan_id' => null,
            'check_in' => $original, 'check_out' => $original->addDays(2),
            'rooms' => 1, 'adults' => 2, 'children' => 0,
            'total' => 400, 'currency' => $type->currency ?: $property->currency,
            'voucher_id' => null, 'discount_total' => 0,
        ]);

        $change = app(BookingModificationService::class)->request($booking, $guest, 'date_change', [
            'check_in' => $new->toDateString(), 'check_out' => $new->addDays(2)->toDateString(),
        ]);

        return [$property, $type, $booking, $change, $guest, $staff, $new, $original];
    }

    public function test_guest_accepts_equal_price_date_move_once(): void
    {
        [$property, $type, $booking, $change, $guest, $staff, $new] = $this->fixtures();
        $service = app(BookingAmendmentOfferService::class);
        $quote = app(AzariPricingEngine::class)->quote($property, $new, $new->addDays(2), [], $type, null, 1);
        $booking->forceFill(['total' => $quote['total'], 'currency' => $quote['currency']])->save();

        $offer = $service->offer($booking->fresh(), $change, $staff);
        $this->assertSame('quoted', $offer->status);
        $this->assertNotNull($offer->quote_expires_at);
        $accepted = $service->accept($booking->fresh(), $offer, $guest);
        $this->assertSame('approved', $accepted->status);
        $this->assertSame($new->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertEqualsWithDelta((float) $quote['total'], (float) $booking->fresh()->total, 0.01);

        try {
            $service->accept($booking->fresh(), $accepted, $guest);
            $this->fail('Guest must not apply the same offer twice.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('change', $e->errors());
        }
    }

    public function test_inventory_taken_after_quote_does_not_move_original_booking(): void
    {
        [$property, $type, $booking, $change, $guest, $staff, $new, $original] = $this->fixtures();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking, $change, $staff);

        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $type->id,
            'status' => 'confirmed',
            'check_in' => $new, 'check_out' => $new->addDays(2),
            'rooms' => 1,
        ]);

        try {
            $service->accept($booking->fresh(), $offer->fresh(), $guest);
            $this->fail('Amendment must not oversell the newly occupied dates.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('change', $e->errors());
        }
        $this->assertSame($original->toDateString(), $booking->fresh()->check_in->toDateString());
    }

    public function test_guest_cannot_accept_an_increased_amount_without_payment(): void
    {
        [$property, $type, $booking, $change, $guest, $staff, $new, $original] = $this->fixtures();
        $quote = app(AzariPricingEngine::class)->quote($property, $new, $new->addDays(2), [], $type, null, 1);
        $booking->forceFill(['total' => max(0, (float) $quote['total'] - 50)])->save();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking->fresh(), $change, $staff);

        try {
            $service->accept($booking->fresh(), $offer, $guest);
            $this->fail('New dates must not be confirmed before the additional payment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }
        $this->assertSame($original->toDateString(), $booking->fresh()->check_in->toDateString());
    }

    public function test_foreign_guest_cannot_accept_or_leak_an_offer(): void
    {
        [$property, $type, $booking, $change, $guest, $staff] = $this->fixtures();
        $offer = app(BookingAmendmentOfferService::class)->offer($booking, $change, $staff);
        $stranger = User::factory()->create();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BookingAmendmentOfferService::class)->accept($booking, $offer, $stranger);
    }

    public function test_guest_acceptance_rejects_expired_quote(): void
    {
        [$property, $type, $booking, $change, $guest, $staff] = $this->fixtures();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking, $change, $staff);
        $offer->update(['quote_expires_at' => now()->subSecond()]);
        $this->expectException(ValidationException::class);
        $service->accept($booking->fresh(), $offer->fresh(), $guest);
    }
}
