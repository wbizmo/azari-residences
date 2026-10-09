<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyOperationsTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerOperationsTriageMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_only_own_property_active_and_overdue_tasks(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $other = Property::factory()->create();

        foreach ([
            ['property_id' => $property->id, 'status' => 'open', 'priority' => 'urgent', 'due_at' => now()->subHour()],
            ['property_id' => $property->id, 'status' => 'blocked', 'priority' => 'normal', 'due_at' => null],
            ['property_id' => $property->id, 'status' => 'completed', 'priority' => 'high', 'due_at' => now()->subHour()],
            ['property_id' => $other->id, 'status' => 'open', 'priority' => 'high', 'due_at' => now()->subHour()],
        ] as $row) {
            PropertyOperationsTask::query()->create([
                ...$row,
                'type' => 'housekeeping',
                'title' => 'Prepare room for next reservation',
                'created_by' => $owner->id,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('user.owner.phase2.operations', $property))
            ->assertOk()
            ->assertViewHas('metrics', function ($metrics): bool {
                return (int) $metrics->total === 3
                    && (int) $metrics->active === 2
                    && (int) $metrics->overdue === 1
                    && (int) $metrics->blocked === 1
                    && (int) $metrics->high_priority === 1
                    && (int) $metrics->unassigned === 2;
            })
            ->assertSeeText('Overdue tasks')
            ->assertSeeText('Blocked tasks');
    }
}
