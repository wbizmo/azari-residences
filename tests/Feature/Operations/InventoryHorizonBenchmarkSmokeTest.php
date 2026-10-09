<?php

namespace Tests\Feature\Operations;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryHorizonBenchmarkSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_benchmark_reports_7_30_365_day_horizons_and_latency_percentiles(): void
    {
        $property = Property::factory()->create([
            'status' => 'available', 'is_published' => true,
        ]);
        $type = $property->accommodationTypes()->firstOrFail();

        $this->assertSame(0, Artisan::call('resavar:benchmark-inventory', [
            'accommodationType' => $type->getKey(),
            '--runs' => 2,
            '--json' => true,
        ]));
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['7', '30', '365'], array_keys($result['horizons']));
        foreach ([7, 30, 365] as $horizon) {
            $row = $result['horizons'][$horizon];
            $this->assertSame(2, $row['samples']);
            $this->assertGreaterThanOrEqual(0, $row['p50_ms']);
            $this->assertGreaterThanOrEqual($row['p50_ms'], $row['p95_ms']);
            $this->assertGreaterThanOrEqual($row['p95_ms'], $row['p99_ms']);
            $this->assertGreaterThanOrEqual(0, $row['max_queries']);
        }
    }

    public function test_unbounded_sampling_and_invalid_date_are_refused(): void
    {
        $this->assertSame(1, Artisan::call('resavar:benchmark-inventory', [
            'accommodationType' => 1,
            '--runs' => 999,
        ]));
        $this->assertSame(1, Artisan::call('resavar:benchmark-inventory', [
            'accommodationType' => 1,
            '--check-in' => '2028-02-30',
        ]));
    }
}
