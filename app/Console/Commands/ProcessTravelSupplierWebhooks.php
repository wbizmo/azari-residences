<?php

namespace App\Console\Commands;

use App\Services\Travel\TravelSupplierEventProcessor;
use Illuminate\Console\Command;

final class ProcessTravelSupplierWebhooks extends Command
{
    protected $signature = 'resavar:process-travel-webhooks {--limit=50}';
    protected $description = 'Process authenticated, deduplicated travel supplier status events';

    public function handle(TravelSupplierEventProcessor $processor): int
    {
        if (! config('travel.webhooks_enabled', false)) {
            $this->components->info('Travel partner callbacks are disabled.');
            return self::SUCCESS;
        }

        $limit = min(max((int) $this->option('limit'), 1), 100);
        $result = $processor->process($limit);
        $this->components->info('Processed: '.$result['processed'].'; rejected: '.$result['rejected']);
        return self::SUCCESS;
    }
}
