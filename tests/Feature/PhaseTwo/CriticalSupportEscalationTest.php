<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CriticalSupportEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_critical_ticket_escalates_once_to_authorized_staff(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $guest = User::factory()->create(['is_admin' => false]);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-CRITICAL-1',
            'user_id' => $guest->id,
            'category' => 'booking',
            'subject' => 'Arrival issue',
            'status' => 'open',
            'severity' => 'unable_to_check_in',
            'priority' => 'urgent',
            'sla_due_at' => now()->subMinutes(10),
        ]);

        $this->assertSame(0, Artisan::call('resavar:escalate-overdue-support'));
        $this->assertNotNull($ticket->fresh()->sla_alerted_at);
        $this->assertSame(0, Artisan::call('resavar:escalate-overdue-support'));
        $matches = Notification::sent($admin, PremiumMailNotification::class)
            ->filter(fn ($notification) => $notification->template === 'support-critical-sla');
        $this->assertCount(1, $matches);
        $this->assertCount(0, Notification::sent($guest, PremiumMailNotification::class)
            ->filter(fn ($notification) => $notification->template === 'support-critical-sla'));
    }

    public function test_noncritical_ticket_does_not_trigger_urgent_escalation(): void
    {
        Notification::fake();
        User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-GENERAL-1', 'user_id' => User::factory()->create()->id,
            'category' => 'general', 'subject' => 'General enquiry',
            'status' => 'open', 'severity' => 'general', 'priority' => 'normal',
            'sla_due_at' => now()->subHour(),
        ]);

        Artisan::call('resavar:escalate-overdue-support');
        $this->assertNull($ticket->fresh()->sla_alerted_at);
    }
}
