<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAssigneeGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_account_cannot_be_made_support_assignee(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(), 'is_active' => true,
            'is_admin' => true, 'staff_role' => 'administrator',
        ]);
        $guest = User::factory()->create(['is_admin' => false, 'staff_role' => null]);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-ASSIGN-1', 'user_id' => $guest->id,
            'subject' => 'Booking support', 'category' => 'booking',
            'severity' => 'general', 'status' => 'open', 'priority' => 'normal',
        ]);

        $this->actingAs($admin)->put(route('azari.admin.support.update', $ticket), [
            'status' => 'open', 'priority' => 'normal',
            'assigned_to' => $guest->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_active_staff_can_be_assigned_to_support_case(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(), 'is_active' => true,
            'is_admin' => true, 'staff_role' => 'administrator',
        ]);
        $staff = User::factory()->create(['is_active' => true, 'staff_role' => 'support']);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-ASSIGN-2', 'user_id' => User::factory()->create()->id,
            'subject' => 'Booking support', 'category' => 'booking',
            'severity' => 'general', 'status' => 'open', 'priority' => 'normal',
        ]);

        $this->actingAs($admin)->put(route('azari.admin.support.update', $ticket), [
            'status' => 'open', 'priority' => 'normal',
            'assigned_to' => $staff->id,
        ])->assertRedirect();

        $this->assertSame($staff->id, $ticket->fresh()->assigned_to);
    }
}
