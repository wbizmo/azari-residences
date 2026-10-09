<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NoMobileNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_notifications_only_use_email_and_database_even_with_old_opt_ins(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'email_notifications' => true,
            'sms_notifications' => true,
            'whatsapp_notifications' => true,
            'phone' => '+2348012345678',
        ]);
        $notice = new PremiumMailNotification('booking-message', 'A booking update');
        $this->assertEqualsCanonicalizing(['mail', 'database'], $notice->via($user));
        $this->assertSame([], (new PremiumMailNotification(
            'booking-message', 'A booking update', onlyChannel: 'sms'
        ))->via($user));
        $this->assertSame([], (new PremiumMailNotification(
            'booking-message', 'A booking update', onlyChannel: 'whatsapp'
        ))->via($user));
    }

    public function test_browser_push_subscription_routes_are_not_exposed(): void
    {
        $this->assertFalse(Route::has('user.push.subscribe'));
        $this->assertFalse(Route::has('user.push.unsubscribe'));
    }

    public function test_profile_preference_save_disables_old_sms_and_whatsapp_choices(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->patch(route('user.preferences.update'), [
            'email_notifications' => 1, 'sms_notifications' => 1,
            'whatsapp_notifications' => 1,
        ])->assertRedirect();

        $this->assertFalse((bool) $user->fresh()->sms_notifications);
        $this->assertFalse((bool) $user->fresh()->whatsapp_notifications);
    }
}
