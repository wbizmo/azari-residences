<?php

namespace Tests\Feature\Bookings;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingAmendmentOfferService;
use App\Services\Bookings\BookingModificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomChangeAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private function setupStay(): array
    {
        $property = Property::factory()->create(['is_published' => true, 'status' => 'available', 'currency' => 'NGN']);
        $oldType = $property->accommodationTypes()->firstOrFail();
        $oldType->update(['total_inventory' => 2, 'base_rate' => 200, 'weekend_rate' => 200, 'currency' => 'NGN']);
        $target = $oldType->replicate();
        $target->fill([
            'name' => 'New Superior Room',
            'slug' => 'new-superior-room-'.uniqid(),
            'code' => 'SUPERIOR-'.uniqid(),
            'total_inventory' => 1,
            'base_rate' => 200, 'weekend_rate' => 200,
            'currency' => 'NGN',
            'is_active' => true, 'is_published' => true,
        ]);
        $target->save();
        $guest = User::factory()->create();
        $staff = User::factory()->create([
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
        ]);
        $start = CarbonImmutable::today()->addDays(30);
        $end = $start->addDays(2);
        $newPrice = app(AzariPricingEngine::class)->quote($property, $start, $end, [], $target, null, 1);
        $booking = Booking::factory()->for($property)->create([
            'user_id' => $guest->getKey(), 'status' => 'confirmed',
            'accommodation_type_id' => $oldType->getKey(), 'rate_plan_id' => null,
            'check_in' => $start, 'check_out' => $end,
            'adults' => 2, 'children' => 0, 'rooms' => 1,
            'currency' => 'NGN', 'total' => $newPrice['total'],
            'discount_total' => 0, 'voucher_id' => null,
        ]);
        $change = app(BookingModificationService::class)->request($booking, $guest, 'room_change', [
            'accommodation_type_id' => $target->getKey(),
        ]);
        return [$property, $oldType, $target, $booking, $change, $guest, $staff, $start, $newPrice];
    }

    public function test_guest_consents_to_new_room_and_policy_while_original_room_is_released(): void
    {
        [$property, $oldType, $target, $booking, $change, $guest, $staff, $start, $expected] = $this->setupStay();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking, $change, $staff);
        $this->assertSame('quoted', $offer->status);
        $this->assertSame($target->getKey(), $offer->price_quote['new_accommodation_type_id']);
        $this->assertSame($target->getKey(), $offer->price_quote['quote']['accommodation_type_id']);
        $this->assertSame($oldType->getKey(), $booking->fresh()->accommodation_type_id);

        $accepted = $service->accept($booking->fresh(), $offer->fresh(), $guest);
        $this->assertSame('approved', $accepted->status);
        $this->assertSame($target->getKey(), $booking->fresh()->accommodation_type_id);
        $this->assertSame($target->name, $booking->fresh()->accommodation_type_name_snapshot);
        $this->assertSame($start->toDateString(), $booking->fresh()->check_in->toDateString());
        $this->assertEqualsWithDelta((float) $expected['total'], (float) $booking->fresh()->total, 0.01);
        $this->assertEquals($expected['policy'], $booking->fresh()->policy_snapshot);
        $this->expectException(ValidationException::class);
        $service->accept($booking->fresh(), $accepted, $guest);
    }

    public function test_taken_new_room_rejects_swap_without_changing_original_type(): void
    {
        [$property, $oldType, $target, $booking, $change, $guest, $staff, $start] = $this->setupStay();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking, $change, $staff);
        Booking::factory()->for($property)->create([
            'accommodation_type_id' => $target->getKey(), 'status' => 'confirmed',
            'check_in' => $start, 'check_out' => $start->addDays(2), 'rooms' => 1,
        ]);
        try {
            $service->accept($booking->fresh(), $offer, $guest);
            $this->fail('Room must not be oversold after offer.');
        } catch (ValidationException) {
            $this->assertSame($oldType->getKey(), $booking->fresh()->accommodation_type_id);
            $this->assertSame('quoted', $offer->fresh()->status);
        }
    }

    public function test_room_in_another_property_cannot_be_selected(): void
    {
        [$property, $oldType, $target, $booking, $change, $guest, $staff] = $this->setupStay();
        $foreign = Property::factory()->create();
        $change->forceFill(['requested_changes' => [
            'accommodation_type_id' => $foreign->accommodationTypes()->firstOrFail()->getKey(),
        ]])->save();

        $this->expectException(ValidationException::class);
        app(BookingAmendmentOfferService::class)->offer($booking, $change, $staff);
    }

    public function test_new_room_is_unpublished_after_offer_so_booking_remains_unchanged(): void
    {
        [$property, $oldType, $target, $booking, $change, $guest, $staff] = $this->setupStay();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking, $change, $staff);
        $target->forceFill(['is_published' => false])->save();
        try {
            $service->accept($booking->fresh(), $offer, $guest);
            $this->fail('Staff-unpublished room should not be reserved.');
        } catch (ValidationException) {
            $this->assertSame($oldType->getKey(), $booking->fresh()->accommodation_type_id);
        }
    }

    public function test_changed_room_rate_requires_new_guest_price_acceptance(): void
    {
        [$property, $oldType, $target, $booking, $change, $guest, $staff] = $this->setupStay();
        $service = app(BookingAmendmentOfferService::class);
        $offer = $service->offer($booking, $change, $staff);
        $target->forceFill(['base_rate' => 850, 'weekend_rate' => 850])->save();
        $this->expectException(ValidationException::class);
        $service->accept($booking->fresh(), $offer, $guest);
    }
}
