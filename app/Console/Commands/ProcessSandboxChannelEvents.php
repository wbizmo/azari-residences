<?php
namespace App\Console\Commands;

use App\Services\PhaseThree\ChannelSandboxEventProcessor;
use App\Services\PhaseThree\ChannelSandboxOutboxDispatcher;
use Illuminate\Console\Command;

/** Manually triggered, deliberately never added to a production scheduler. */
final class ProcessSandboxChannelEvents extends Command
{
    protected $signature = 'resavar:channels:process-sandbox {--limit=25}';
    protected $description = 'Process simulated partner events locally; never publishes to a real provider.';

    public function handle(ChannelSandboxEventProcessor $inbox, ChannelSandboxOutboxDispatcher $outbox): int
    {
        if (! config('reserva.channels.sandbox_webhooks_enabled', false)
            || app()->environment('production')) {
            $this->error('Sandbox processing is disabled; no external provider actions were performed.');
            return self::FAILURE;
        }
        $limit = max(1,min((int)$this->option('limit'),100));
        $received = $inbox->drain($limit);
        $dispatched = $outbox->drain($limit);
        $this->line('Inbox processed: '.count($received).'. Outbox simulated: '.count($dispatched).'.');
        return self::SUCCESS;
    }
}
