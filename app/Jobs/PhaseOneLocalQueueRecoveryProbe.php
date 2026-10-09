<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Test-only queued side-effect. Killing its first worker after the database
 * write but before ACK exercises at-least-once delivery safely.
 */
class PhaseOneLocalQueueRecoveryProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(
        public readonly string $token,
        public readonly string $firstAttemptSignal,
    ) {
        $this->onConnection('database');
    }

    public function handle(): void
    {
        if (! app()->environment('testing') || ! preg_match('/^[a-f0-9]{32}$/', $this->token)
            || ! str_starts_with($this->firstAttemptSignal, sys_get_temp_dir().DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Local queue probe cannot run outside an isolated test environment.');
        }

        // Unique component key is an actual database constraint; no external
        // payment, notification or other irreversible side effect occurs.
        DB::table('system_heartbeats')->insertOrIgnore([
            'component' => 'phase1-probe-'.$this->token,
            'last_seen_at' => now(),
            'metadata' => json_encode(['synthetic_effects' => 1], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($this->job?->attempts() === 1) {
            if (file_put_contents($this->firstAttemptSignal, 'side-effect-committed') === false) {
                throw new \RuntimeException('Cannot signal local worker recovery probe.');
            }
            // Harness intentionally SIGKILLs this *only* spawned test worker
            // after the idempotent DB effect, before queue acknowledgement.
            sleep(12);
        }
    }
}
