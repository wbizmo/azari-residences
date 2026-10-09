<?php

namespace App\Console\Commands;

use App\Models\AccommodationType;
use App\Services\Bookings\AzariAvailabilityEngine;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkInventoryHorizon extends Command
{
    protected $signature = 'resavar:benchmark-inventory
        {accommodationType : Existing accommodation-type ID}
        {--runs=10 : Number of read-only samples (1-50)}
        {--check-in= : Optional ISO date; defaults to 21 days from now}
        {--json : Output one JSON object}';

    protected $description = 'Read-only 7/30/365-night inventory query count, p50/p95/p99 and memory baseline.';

    public function handle(AzariAvailabilityEngine $engine): int
    {
        $id = filter_var($this->argument('accommodationType'), FILTER_VALIDATE_INT);
        $runs = (int) $this->option('runs');
        $date = (string) ($this->option('check-in') ?: today()->addDays(21)->toDateString());

        if (! $id || $id < 1 || $runs < 1 || $runs > 50
            || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
            $this->error('Pass a valid accommodation type, 1-50 runs and an ISO check-in date.');
            return self::FAILURE;
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $date);
            if (! $start || $start->toDateString() !== $date) {
                throw new \InvalidArgumentException('Invalid date.');
            }
        } catch (\Throwable) {
            $this->error('Check-in is not a real ISO calendar date.');
            return self::FAILURE;
        }
        $type = AccommodationType::query()->find($id);
        if (! $type) {
            $this->error('The requested accommodation type does not exist.');
            return self::FAILURE;
        }

        $queries = 0;
        DB::listen(static function () use (&$queries): void { $queries++; });
        $results = [];

        foreach ([7, 30, 365] as $nights) {
            $latencies = [];
            $counts = [];
            for ($i = 0; $i < $runs; $i++) {
                $queries = 0;
                $began = hrtime(true);
                $remaining = $engine->remainingByDate($type, $start, $start->addDays($nights));
                $latencies[] = round((hrtime(true) - $began) / 1_000_000, 3);
                $counts[] = $queries;
                if ($remaining->count() !== $nights) {
                    $this->error('Inventory projection did not cover the requested stay horizon.');
                    return self::FAILURE;
                }
            }
            sort($latencies, SORT_NUMERIC);
            sort($counts, SORT_NUMERIC);
            $n = count($latencies);
            $results[(string) $nights] = [
                'nights' => $nights,
                'samples' => $runs,
                'p50_ms' => $latencies[(int) ceil($n * 0.5) - 1],
                'p95_ms' => $latencies[(int) ceil($n * 0.95) - 1],
                'p99_ms' => $latencies[(int) ceil($n * 0.99) - 1],
                'min_queries' => min($counts),
                'max_queries' => max($counts),
                'p95_queries' => $counts[(int) ceil($n * 0.95) - 1],
            ];
        }

        $payload = [
            'database_driver' => DB::connection()->getDriverName(),
            'accommodation_type_id' => $type->getKey(),
            'check_in' => $date,
            'horizons' => $results,
            'php_peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'note' => 'Read-only local/staging baseline, not an externally validated production capacity or concurrency result.',
        ];
        $this->line(json_encode($payload, $this->option('json') ? JSON_THROW_ON_ERROR
            : JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
