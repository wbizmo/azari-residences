<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyLocalStayReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_arrival_checkpoint_uses_booking_property_timezone_at_date_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 23:45:00', 'UTC'));
        config()->set('localization.platform_timezone', 'UTC');
        try {
            $owner = User::factory()->create();
            $guest = User::factory()->create(['email_verified_at' => now()]);
            $property = Property::factory()->create(['owner_id' => $owner->id]);

            // In Kiritimati it is already Sunday, 11 October, so a
            // 12 October arrival is tomorrow, not two days away.
            Booking::factory()->create([
                'property_id' => $property->id,
                'user_id' => $guest->id,
                'status' => 'confirmed',
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-14',
                'property_timezone' => 'Pacific/Kiritimati',
            ]);

            $this->artisan('azari:send-transactional-reminders --dry-run')
                ->expectsOutput('Arrival: 1')
                ->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_arrival_outside_local_checkpoint_is_not_counted_early(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 23:45:00', 'UTC'));
        config()->set('localization.platform_timezone', 'UTC');
        try {
            $guest = User::factory()->create(['email_verified_at' => now()]);
            $property = Property::factory()->create();

            Booking::factory()->create([
                'property_id' => $property->id,
                'user_id' => $guest->id,
                'status' => 'confirmed',
                'check_in' => '2026-10-12',
                'check_out' => '2026-10-14',
                'property_timezone' => 'America/Los_Angeles',
            ]);

            // Los Angeles is still on 10 October.
            $this->artisan('azari:send-transactional-reminders --dry-run')
                ->expectsOutput('Arrival: 0')
                ->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }
    }
}
