<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;

class SystemHeartbeatCommand extends Command
{
    protected $signature = 'azari:heartbeat';
    protected $description = 'Record scheduler health and dispatch a queue-worker heartbeat.';
    public function handle(): int
    {
        SystemHeartbeat::query()->updateOrCreate(['component'=>'scheduler'], ['last_seen_at'=>now(),'metadata'=>['host'=>gethostname() ?: null]]);
        RecordQueueHeartbeat::dispatch();
        $this->info('Scheduler heartbeat recorded; queue heartbeat dispatched.');
        return self::SUCCESS;
    }
}
