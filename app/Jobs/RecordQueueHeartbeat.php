<?php

namespace App\Jobs;

use App\Models\SystemHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;
    public function handle(): void { SystemHeartbeat::query()->updateOrCreate(['component'=>'queue'], ['last_seen_at'=>now(),'metadata'=>['connection'=>config('queue.default')]]); }
}
