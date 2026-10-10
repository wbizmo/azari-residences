<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\AccommodationType;
use App\Models\InventoryDate;
use App\Models\Property;
use App\Services\Bookings\InventoryBulkUpdateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryUndoConflictGuardTest extends TestCase
{
    use RefreshDatabase;

    private function room(): AccommodationType
    {
        $property = Property::factory()->create();
        return AccommodationType::query()->create([
            'property_id' => $property->id,
            'name' => 'Private room',
            'slug' => 'private-room',
            'code' => 'PR1',
            'adult_capacity' => 2,
            'child_capacity' => 0,
            'max_guests' => 2,
            'total_inventory' => 4,
            'base_rate' => 120,
            'currency' => 'NGN',
            'is_active' => true,
            'is_published' => true,
        ]);
    }

    public function test_undo_refuses_overwriting_later_staff_price_edit(): void
    {
        $room = $this->room();
        $day = CarbonImmutable::now()->addDays(14)->startOfDay();
        $service = app(InventoryBulkUpdateService::class);
        $log = $service->apply($room, $day, $day, ['price_override' => 120], null);

        $this->assertNotEmpty($log->fresh()->after_snapshot);
        InventoryDate::query()->where('accommodation_type_id', $room->id)
            ->whereDate('date', $day->toDateString())
            ->update(['price_override' => 220]);

        try {
            $service->undo($log->fresh(), null);
            $this->fail('Undo should reject an intervening staff edit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inventory', $exception->errors());
        }

        $this->assertSame('220.00', InventoryDate::query()
            ->where('accommodation_type_id', $room->id)->firstOrFail()->price_override);
        $this->assertNull($log->fresh()->reverted_at);
    }

    public function test_preview_revision_rejects_stale_staff_editor_after_calendar_change(): void
    {
        $room = $this->room();
        $day = CarbonImmutable::today()->addDays(22);
        $service = app(InventoryBulkUpdateService::class);
        $preview = $service->preview($room, $day, $day, ['price_override' => 180]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $preview['revision']);
        $service->apply($room, $day, $day, ['price_override' => 150], null, 'owner');

        try {
            $service->apply($room, $day, $day, ['price_override' => 180],
                null, 'owner', $preview['revision']);
            $this->fail('A stale preview must not overwrite another calendar edit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_revision', $exception->errors());
        }

        $this->assertSame('150.00', InventoryDate::query()
            ->where('accommodation_type_id', $room->id)->firstOrFail()->price_override);
    }

    public function test_unchanged_preview_revision_allows_atomic_calendar_update(): void
    {
        $room = $this->room();
        $day = CarbonImmutable::today()->addDays(25);
        $service = app(InventoryBulkUpdateService::class);
        $preview = $service->preview($room, $day, $day, ['stop_sell' => true]);

        $log = $service->apply($room, $day, $day,
            ['stop_sell' => true], null, 'owner', $preview['revision']);

        $this->assertNotNull($log->id);
        $this->assertTrue((bool) InventoryDate::query()
            ->where('accommodation_type_id', $room->id)->firstOrFail()->stop_sell);
    }

    public function test_preview_revision_covers_full_calendar_horizon(): void
    {
        $room = $this->room();
        $from = CarbonImmutable::today()->addDays(10);
        $to = $from->addDays(366);
        $service = app(InventoryBulkUpdateService::class);
        $preview = $service->preview($room, $from, $to, ['stop_sell' => true]);

        $this->assertSame(367, $preview['days']);
        $this->assertSame($service->revision($room, $from, $to), $preview['revision']);
    }

    public function test_untouched_new_inventory_date_can_be_undone_once(): void
    {
        $room = $this->room();
        $day = CarbonImmutable::now()->addDays(15)->startOfDay();
        $service = app(InventoryBulkUpdateService::class);
        $log = $service->apply($room, $day, $day, ['stop_sell' => true], null);

        $service->undo($log->fresh(), null);
        $this->assertDatabaseMissing('inventory_dates', [
            'accommodation_type_id' => $room->id,
            'date' => $day->toDateString(),
        ]);
        $this->assertNotNull($log->fresh()->reverted_at);
    }

    public function test_preview_rejects_an_empty_update_instead_of_enabling_apply(): void
    {
        $room = $this->room();
        $day = CarbonImmutable::today()->addDays(14);
        $service = app(InventoryBulkUpdateService::class);

        try {
            $service->preview($room, $day, $day, []);
            $this->fail('Empty inventory edits must not produce an applicable preview.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inventory', $exception->errors());
        }
    }

    public function test_owner_calendar_preview_script_is_inside_rendered_blade_content(): void
    {
        $source = file_get_contents(resource_path('views/user/owner/commercial.blade.php'));
        $formPosition = strpos($source, 'data-inventory-calendar-form');
        $scriptPosition = strpos($source, '<script>');
        $endSectionPosition = strrpos($source, '@endsection');

        $this->assertNotFalse($formPosition);
        $this->assertNotFalse($scriptPosition);
        $this->assertNotFalse($endSectionPosition);
        $this->assertGreaterThan($formPosition, $scriptPosition,
            'The script must execute after the calendar form has rendered.');
        $this->assertGreaterThan($scriptPosition, $endSectionPosition,
            'The script must be included in the Blade content section, not emitted before its layout.');
    }
}
