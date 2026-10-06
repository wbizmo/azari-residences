<?php

namespace App\Http\Controllers;

use App\Models\SystemHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function (): void { Cache::put('resarva-readiness', 'ok', 15); Cache::get('resarva-readiness'); }),
            'storage' => $this->check(function (): void { Storage::disk('local')->put('health/readiness.tmp', 'ok'); Storage::disk('local')->delete('health/readiness.tmp'); }),
        ];

        if (Schema::hasTable('system_heartbeats')) {
            $scheduler = SystemHeartbeat::query()->where('component', 'scheduler')->first();
            $queue = SystemHeartbeat::query()->where('component', 'queue')->first();
            $checks['scheduler'] = $scheduler?->last_seen_at?->gte(now()->subMinutes(5)) ?? false;
            if (config('queue.default') !== 'sync') {
                $checks['queue'] = $queue?->last_seen_at?->gte(now()->subMinutes(10)) ?? false;
            }
        }

        $ok = collect($checks)->every(fn (bool $value) => $value);

        return response()->json(['status' => $ok ? 'ready' : 'degraded', 'checks' => $checks], $ok ? 200 : 503);
    }

    private function check(callable $callback): bool
    {
        try { $callback(); return true; } catch (\Throwable) { return false; }
    }
}
