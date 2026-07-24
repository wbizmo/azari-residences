<?php

namespace App\Console\Commands;

use App\Services\Bookings\AzariBookingAutomation;
use Illuminate\Console\Command;

class SyncAzariBookingStatuses extends Command
{
    protected $signature = 'azari:sync-bookings';
    protected $description = 'Automatically move paid bookings into check-in and completed states.';

    public function handle(AzariBookingAutomation $automation): int
    {
        $result = $automation->run();
        $this->info("Check-ins: {$result['checkIns']}; completed: {$result['completed']}");
        return self::SUCCESS;
    }
}
