<?php

namespace Tests\Feature\Admin;

use App\Models\InventoryChangeLog;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCalendarInventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_apply_and_safely_undo_a_nightly_override(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $property = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $type = $property->accommodationTypes()->firstOrFail();
        $day = CarbonImmutable::today()->addDays(48)->toDateString();
        $changes = [
            'from_date' => $day,
            'to_date' => $day,
            'price_override' => '145.50',
        ];

        $this->actingAs($admin)->get(route('azari.admin.properties.commercial', $property))
            ->assertOk()->assertSee('Availability and nightly rate calendar');

        $response = $this->actingAs($admin)->postJson(
            route('azari.admin.properties.commercial.calendar.preview', [$property, $type]), $changes
        )->assertOk();

        $revision = $response->json('revision');
        $this->assertIsString($revision);
        $this->assertSame(64, strlen($revision));

        $this->actingAs($admin)->post(
            route('azari.admin.properties.commercial.calendar.apply', [$property, $type]),
            [...$changes, 'expected_revision' => $revision]
        )->assertRedirect();

        $this->assertDatabaseHas('inventory_dates', [
            'accommodation_type_id' => $type->id,
            'date' => $day,
            'price_override' => 145.50,
        ]);

        $log = InventoryChangeLog::query()->where('property_id', $property->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(
            route('azari.admin.properties.commercial.calendar.undo', [$property, $log])
        )->assertRedirect();
        $this->assertNotNull($log->fresh()->reverted_at);
        $this->assertDatabaseMissing('inventory_dates', [
            'accommodation_type_id' => $type->id,
            'date' => $day,
        ]);
    }

    public function test_admin_cannot_edit_room_type_belonging_to_another_property(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $first = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $other = Property::factory()->create(['status' => 'available', 'is_published' => true]);
        $foreign = $other->accommodationTypes()->firstOrFail();
        $day = CarbonImmutable::today()->addDays(50)->toDateString();

        $this->actingAs($admin)->postJson(
            route('azari.admin.properties.commercial.calendar.preview', [$first, $foreign]),
            ['from_date' => $day, 'to_date' => $day, 'stop_sell' => 1]
        )->assertNotFound();
    }
}
