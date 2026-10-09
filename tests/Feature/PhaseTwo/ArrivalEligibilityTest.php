<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\Payment;
use App\Models\PropertyOperationsTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArrivalEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_check_in_requires_identity_and_recorded_room_readiness(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->id, 'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'property_timezone' => config('localization.platform_timezone', 'UTC'),
            'status' => 'confirmed',
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id, 'provider' => 'manual',
            'reference' => 'PHASE2-ARRIVAL-PAID', 'status' => Payment::SUCCESSFUL,
            'currency' => $booking->currency, 'amount' => $booking->total,
            'verified_at' => now(),
        ]);

        $this->assertFalse($booking->isCheckInEligible(), 'Identity and readiness must be required');
        IdentityVerification::query()->create([
            'user_id' => $guest->id, 'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => (string) Str::uuid(), 'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        $this->assertFalse($booking->isCheckInEligible(), 'Identity alone must not bypass room readiness');

        PropertyOperationsTask::query()->create([
            'property_id' => $booking->property_id, 'booking_id' => $booking->id,
            'type' => 'housekeeping', 'title' => 'Clean and prepare guest room',
            'status' => 'completed', 'priority' => 'normal', 'completed_at' => now(),
            'created_by' => $guest->id,
        ]);
        $this->assertTrue($booking->isCheckInEligible());

        $this->actingAs($guest)->post(route('user.bookings.phase2.arrival.check-in', $booking->reference))
            ->assertRedirect();
        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertFalse($booking->fresh()->isCheckInEligible());
    }

    public function test_unresolved_maintenance_blocks_self_check_in_even_with_completed_housekeeping(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create([
            'user_id' => $guest->id, 'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'property_timezone' => config('localization.platform_timezone', 'UTC'),
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id, 'provider' => 'manual',
            'reference' => 'PHASE2-ARRIVAL-PAID-2', 'status' => Payment::SUCCESSFUL,
            'currency' => $booking->currency, 'amount' => $booking->total,
            'verified_at' => now(),
        ]);
        IdentityVerification::query()->create([
            'user_id' => $guest->id, 'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => (string) Str::uuid(), 'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        PropertyOperationsTask::query()->create([
            'property_id' => $booking->property_id, 'booking_id' => $booking->id,
            'type' => 'housekeeping', 'title' => 'Prepare room',
            'status' => 'completed', 'priority' => 'normal', 'completed_at' => now(),
            'created_by' => $guest->id,
        ]);
        PropertyOperationsTask::query()->create([
            'property_id' => $booking->property_id, 'booking_id' => $booking->id,
            'type' => 'maintenance', 'title' => 'Repair blocked entry',
            'status' => 'blocked', 'priority' => 'urgent', 'created_by' => $guest->id,
        ]);

        $this->assertFalse($booking->isCheckInEligible());
        $this->actingAs($guest)->post(route('user.bookings.phase2.arrival.check-in', $booking->reference))
            ->assertStatus(422);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }
}
