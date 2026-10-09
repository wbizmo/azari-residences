<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SearchBenchmarkSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_benchmark_emits_valid_latency_and_query_metrics(): void
    {
        $this->assertSame(0, Artisan::call('resavar:benchmark-search', ['--runs' => 2, '--json' => true]));
        $data = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $data['runs']);
        $this->assertGreaterThanOrEqual(0, $data['p95_ms']);
        $this->assertGreaterThanOrEqual(0, $data['max_queries']);
    }

    public function test_rejects_unbounded_benchmark_runs(): void
    {
        $this->assertSame(1, Artisan::call('resavar:benchmark-search', ['--runs' => 999]));
    }
}
