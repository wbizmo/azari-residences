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
}
