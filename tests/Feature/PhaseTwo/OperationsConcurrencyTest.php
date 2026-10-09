<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyOperationsTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_staff_updates_with_same_version_do_not_lose_the_first_change(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $task = PropertyOperationsTask::query()->create([
            'property_id' => $property->id,
            'created_by' => $owner->id,
            'type' => 'housekeeping',
            'title' => 'Prepare unit',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $route = route('user.owner.phase2.operations.tasks.update', [$property, $task]);
        $this->actingAs($owner)->from($route)->patch($route, [
            'status' => 'in_progress', 'version' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('property_operations_tasks', [
            'id' => $task->id, 'status' => 'in_progress', 'version' => 1,
        ]);

        $this->actingAs($owner)->from($route)->patch($route, [
            'status' => 'completed', 'version' => 0,
        ])->assertRedirect()->assertSessionHasErrors('status');

        $this->assertDatabaseHas('property_operations_tasks', [
            'id' => $task->id, 'status' => 'in_progress', 'version' => 1,
        ]);

        $this->actingAs($owner)->from($route)->patch($route, [
            'status' => 'completed', 'version' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('property_operations_tasks', [
            'id' => $task->id, 'status' => 'completed', 'version' => 2,
        ]);
    }
}
