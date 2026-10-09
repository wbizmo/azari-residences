<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\Bookings\AzariBookingAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AzariSprintFiveSixFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_use_admin_routes_and_admin_cannot_use_customer_dashboard(): void
    {
        $customer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'is_admin' => true,
            'staff_role' => 'administrator',
        ]);

        $this->actingAs($customer)
            ->get('/azaridevadmin')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_booking_automation_opens_check_in_and_completes_departed_stays(): void
    {
        $property = Property::factory()->create();

        $arrival = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => 'paid',
            'check_in' => Carbon::today(),
            'check_out' => Carbon::tomorrow(),
            'paid_at' => now(),
        ]);

        $departed = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => 'check_in',
            'check_in' => Carbon::today()->subDays(3),
            'check_out' => Carbon::yesterday(),
            'paid_at' => now(),
        ]);

        app(AzariBookingAutomation::class)->run();

        $this->assertSame('check_in', $arrival->fresh()->status);
        $this->assertSame('completed', $departed->fresh()->status);
    }

    public function test_only_admin_can_cancel_a_booking(): void
    {
        $property = Property::factory()->create();

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => 'paid',
        ]);

        $customer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'is_admin' => true,
            'staff_role' => 'administrator',
        ]);

        $this->actingAs($customer)
            ->put(
                route('azari.admin.bookings.cancel', $booking),
                ['reason' => 'No']
            )
            ->assertNotFound();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->put(
                route('azari.admin.bookings.cancel', $booking),
                ['reason' => 'Property unavailable']
            )
            ->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}