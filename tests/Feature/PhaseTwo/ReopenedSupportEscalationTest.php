<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReopenedSupportEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_reopening_critical_ticket_resets_sla_and_alert_marker(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-REOPEN-1',
            'user_id' => $guest->id,
            'category' => 'booking',
            'subject' => 'Unable to enter residence',
            'status' => 'closed',
            'severity' => 'unable_to_check_in',
            'sla_due_at' => now()->subDay(),
            'sla_alerted_at' => now()->subDay(),
            'closed_at' => now()->subHour(),
        ]);

        $this->actingAs($guest)
            ->patch(route('user.support.reopen', $ticket))
            ->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertNull($fresh->sla_alerted_at);
        $this->assertTrue($fresh->sla_due_at->isFuture());
        $this->assertTrue($fresh->sla_due_at->lessThan(now()->addMinutes(31)));
    }

    public function test_guest_cannot_reopen_someone_elses_ticket(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-REOPEN-2',
            'user_id' => $other->id,
            'category' => 'booking',
            'subject' => 'Closed support question',
            'status' => 'closed',
            'severity' => 'general',
        ]);

        $this->actingAs($guest)->patch(route('user.support.reopen', $ticket))
            ->assertForbidden();
        $this->assertSame('closed', $ticket->fresh()->status);
    }
}
