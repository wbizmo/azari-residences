<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InAppNotificationContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_app_history_renders_the_notification_subject_and_lines(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $guest->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\PremiumMailNotification',
            'data' => [
                'template' => 'booking-message',
                'subject' => 'Property replied to your booking',
                'lines' => ['There is a new private message in your inbox.'],
            ],
        ]);

        $this->actingAs($guest)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSeeText('Property replied to your booking')
            ->assertSeeText('There is a new private message in your inbox.');
    }
}
