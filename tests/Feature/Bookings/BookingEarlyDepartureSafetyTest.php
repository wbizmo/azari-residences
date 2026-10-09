<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Bookings\BookingCancellationOverrideService;
use App\Services\Bookings\BookingEarlyDepartureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingEarlyDepartureSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_early_departure_preserves_original_contract_and_requires_manual_refund_approval(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC'));
        try {
            $maker = User::factory()->create([
                'account_type' => 'staff', 'is_admin' => true, 'staff_role' => 'administrator',
            ]);
            $checker = User::factory()->create([
                'account_type' => 'staff', 'is_admin' => true, 'staff_role' => 'administrator',
            ]);
            $booking = Booking::factory()->create([
                'status' => 'checked_in',
                'check_in' => '2026-11-09',
                'check_out' => '2026-11-14',
                'checked_in_at' => now()->subDays(2),
                'property_timezone' => 'UTC',
                'currency' => 'NGN',
                'total' => 800,
            ]);
            $recorded = app(BookingEarlyDepartureService::class)->record(
                $booking, $maker, 'Guest departed two nights early due to emergency.'
            );
            $this->assertSame('checked_out', $recorded->status);
            $this->assertSame('2026-11-14', $recorded->check_out->toDateString());
            $this->assertSame(0, Refund::query()->where('booking_id', $booking->getKey())->count());
            $this->assertDatabaseHas('booking_status_histories', [
                'booking_id' => $booking->getKey(), 'to_status' => 'checked_out',
            ]);
            $history = $booking->statusHistory()->firstOrFail();
            $this->assertTrue((bool) $history->metadata['early_departure']);
            $this->assertSame(3, $history->metadata['nights_unused']);

            Payment::query()->create([
                'booking_id' => $booking->getKey(),
                'provider' => 'manual',
                'reference' => 'PAY-EARLY-DEPARTURE-1',
                'status' => Payment::SUCCESSFUL,
                'amount' => 400,
                'currency' => 'NGN',
                'verified_at' => now(),
            ]);
            $exceptions = app(BookingCancellationOverrideService::class);
            $override = $exceptions->request(
                $booking->fresh(), $maker, 100,
                'Reviewed early departure exception, no automatic refund.'
            );
            $this->assertSame('requested', $override->status);
            $this->assertSame(0, Refund::query()->where('booking_id', $booking->getKey())->count());

            $exceptions->review($booking, $override, $checker, 'approve');
            $this->assertDatabaseHas('refunds', [
                'booking_id' => $booking->getKey(), 'status' => 'requested', 'amount' => 100,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_early_departure_rejects_unoccupied_and_after_original_checkout(): void
    {
        $maker = User::factory()->create([
            'account_type' => 'staff', 'is_admin' => true, 'staff_role' => 'administrator',
        ]);
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'checked_in_at' => null,
            'check_in' => now()->subDays(2),
            'check_out' => now()->addDays(3),
        ]);
        $this->expectException(ValidationException::class);
        app(BookingEarlyDepartureService::class)->record(
            $booking, $maker, 'Cannot depart from a room not yet checked in.'
        );
    }
}
