<?php

namespace App\Console\Commands;

use App\Services\Search\MarketplaceSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResavarSearchBenchmark extends Command
{
    protected $signature = 'resavar:benchmark-search {--runs=10 : 1 to 50 search iterations} {--check-in= : ISO date} {--check-out= : ISO date} {--json : Print results as JSON}';
    protected $description = 'Read-only search latency and query-count benchmark for staging or controlled load environments.';

    public function handle(MarketplaceSearchService $search): int
    {
        $runs = (int) $this->option('runs');
        if ($runs < 1 || $runs > 50) {
            $this->error('Runs must be 1 to 50.');
            return self::FAILURE;
        }
        $in = (string) ($this->option('check-in') ?: today()->addDays(14)->toDateString());
        $out = (string) ($this->option('check-out') ?: today()->addDays(17)->toDateString());
        try {
            $start = \Carbon\CarbonImmutable::parse($in);
            $end = \Carbon\CarbonImmutable::parse($out);
            if ($end->lessThanOrEqualTo($start) || $end->diffInDays($start) > 30) {
                throw new \InvalidArgumentException('Benchmark stay must be 1 to 30 nights.');
            }
        } catch (\Throwable) {
            $this->error('Use valid ISO check-in/out dates spanning 1 to 30 nights.');
            return self::FAILURE;
        }

        $filter = ['check_in' => $in, 'check_out' => $out, 'adults' => 1, 'children' => 0, 'rooms' => 1];
        $latencies = [];
        $counts = [];
        $queries = 0;
        DB::listen(function () use (&$queries): void { $queries++; });
        for ($n = 0; $n < $runs; $n++) {
            $queries = 0;
            $startTime = hrtime(true);
            $search->search($filter);
            $latencies[] = round((hrtime(true) - $startTime) / 1_000_000, 2);
            $counts[] = $queries;
        }
        sort($latencies, SORT_NUMERIC);
        sort($counts, SORT_NUMERIC);
        $p95 = max(0, (int) ceil(count($latencies) * 0.95) - 1);
        $metrics = [
            'runs' => $runs,
            'p50_ms' => $latencies[(int) floor(($runs - 1) / 2)],
            'p95_ms' => $latencies[$p95],
            'max_queries' => max($counts),
            'environment' => app()->environment(),
            'database_driver' => config('database.default'),
            'note' => 'Synthetic/read-only benchmark; not a production SLA or a concurrency benchmark.',
        ];
        $this->line($this->option('json') ? json_encode($metrics, JSON_THROW_ON_ERROR) : json_encode($metrics, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
