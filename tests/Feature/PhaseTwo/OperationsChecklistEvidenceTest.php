<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyOperationsTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsChecklistEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_requires_checklist_completion_and_keeps_evidence_private(): void
    {
        Storage::fake('private');

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(
            route('user.owner.phase2.operations.tasks.store', $property),
            [
                'type' => 'housekeeping',
                'priority' => 'high',
                'title' => 'Turn over suite 4',
                'checklist_items' => "Clean room\nReplace linen\nInspect bathroom",
            ]
        )->assertRedirect();

        $task = PropertyOperationsTask::query()->where('property_id', $property->id)->sole();
        $this->assertCount(3, $task->checklist);
        $this->assertFalse($task->checklist[0]['done']);

        $route = route('user.owner.phase2.operations.tasks.update', [$property, $task]);

        $this->actingAs($owner)->patch($route, [
            'status' => 'completed',
            'version' => 0,
            'checklist_completed' => [0, 1],
        ])->assertRedirect()->assertSessionHasErrors('status');

        $task->refresh();
        $this->assertSame('open', $task->status);
        $this->assertSame(0, $task->version);

        $this->actingAs($owner)->patch($route, [
            'status' => 'completed',
            'version' => 0,
            'checklist_completed' => [0, 1, 2],
            'evidence' => UploadedFile::fake()->image('room.png', 640, 480),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(1, $task->version);
        $this->assertNotNull($task->completed_at);
        $this->assertTrue(collect($task->checklist)->every(fn (array $item) => $item['done']));
        $this->assertSame('image/png', $task->evidence_mime);
        $this->assertSame('task-evidence-'.$task->id.'.png', $task->evidence_name);
        Storage::disk('private')->assertExists($task->evidence_path);

        $this->actingAs($owner)
            ->get(route('user.owner.phase2.operations.tasks.evidence', [$property, $task]))
            ->assertOk();
    }

    public function test_evidence_route_cannot_cross_property_boundary(): void
    {
        Storage::fake('private');

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $otherOwner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $otherProperty = Property::factory()->create(['owner_id' => $otherOwner->id]);

        $task = PropertyOperationsTask::query()->create([
            'property_id' => $property->id,
            'created_by' => $owner->id,
            'type' => 'inspection',
            'title' => 'Inspect unit',
            'priority' => 'normal',
            'status' => 'open',
            'evidence_path' => 'property-operations/evidence/private.png',
            'evidence_name' => 'task-evidence.png',
            'evidence_mime' => 'image/png',
        ]);
        Storage::disk('private')->put($task->evidence_path, 'png');

        $this->actingAs($otherOwner)
            ->get(route('user.owner.phase2.operations.tasks.evidence', [$otherProperty, $task]))
            ->assertNotFound();
    }
}
