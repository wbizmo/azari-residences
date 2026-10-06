<?php

namespace App\Console\Commands;

use App\Jobs\SyncChannelConnection;
use App\Models\ChannelConnection;
use Illuminate\Console\Command;

class SyncChannels extends Command
{
    protected $signature = 'azari:sync-channels {--inline : Run synchronously instead of dispatching jobs}';
    protected $description = 'Synchronize active external accommodation channel connections.';
    public function handle(): int
    {
        ChannelConnection::query()->where('is_active',true)->where(fn($q)=>$q->whereNull('next_retry_at')->orWhere('next_retry_at','<=',now()))
            ->orderBy('id')->chunkById(50, function($connections): void {
                foreach ($connections as $connection) $this->option('inline') ? SyncChannelConnection::dispatchSync($connection->id) : SyncChannelConnection::dispatch($connection->id);
            });
        $this->info('Eligible channel connections queued for synchronization.');
        return self::SUCCESS;
    }
}
