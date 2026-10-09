<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportSlaMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_support_overview_reports_live_sla_and_assignment_totals(): void
    {
        $admin = User::factory()->create([
            'account_type' => 'staff', 'staff_role' => 'administrator',
            'is_admin' => true, 'is_active' => true, 'email_verified_at' => now(),
        ]);
        $guest = User::factory()->create();
        SupportTicket::query()->create([
            'reference' => 'SUP-METRIC-1', 'user_id' => $guest->id,
            'category' => 'booking', 'subject' => 'Cannot check in', 'status' => 'open',
            'severity' => 'unable_to_check_in', 'priority' => 'urgent',
            'sla_due_at' => now()->subHour(), 'response_due_at' => now()->subMinutes(30),
        ]);
        SupportTicket::query()->create([
            'reference' => 'SUP-METRIC-2', 'user_id' => $guest->id,
            'category' => 'booking', 'subject' => 'Resolved issue', 'status' => 'resolved',
            'severity' => 'general', 'priority' => 'normal',
            'sla_due_at' => now()->subDay(), 'response_due_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('azari.admin.support.index'))
            ->assertOk()
            ->assertSeeText('Breached SLA')
            ->assertSeeText('Critical incidents')
            ->assertSeeText('Unassigned')
            ->assertSeeText('Response overdue');
    }
}
