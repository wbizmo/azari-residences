<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Services\Channels\ChannelSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncChannelConnection implements ShouldQueue
{
    use Queueable;
    public int $tries = 4;
    public function __construct(public int $connectionId) {}
    public function backoff(): array { return [60, 300, 900]; }
    public function handle(ChannelSyncService $sync): void
    {
        $connection = ChannelConnection::query()->find($this->connectionId);
        if ($connection?->is_active) $sync->sync($connection);
    }
}
