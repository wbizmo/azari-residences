<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditServiceTransactions extends Command
{
    protected $signature = 'azari:service-transaction-audit
        {--strict : Fail for service-boundary and transaction-safety regressions}';

    protected $description = 'Audit Azari service boundaries and critical transaction protections.';

    public function handle(): int
    {
        $issues = [];

        $controller = app_path('Http/Controllers/PublicSite/AzariBookingFlowController.php');
        $controllerContent = File::get($controller);

        if (! str_contains($controllerContent, 'BookingCreationService')) {
            $issues[] = 'Booking creation is not delegated to BookingCreationService.';
        }

        if (str_contains($controllerContent, 'DB::transaction(')) {
            $issues[] = 'Booking controller still owns a direct transaction boundary.';
        }

        $critical = [
            'Bookings/BookingCreationService.php',
            'Bookings/AzariAvailabilityEngine.php',
            'Payments/PaymentInitiator.php',
            'Payments/PaymentFinalizer.php',
            'Owners/OwnerWithdrawalService.php',
        ];

        foreach ($critical as $relative) {
            $file = app_path('Services/'.$relative);
            if (! File::exists($file)) {
                $issues[] = "Missing app/Services/{$relative}.";
                continue;
            }

            $content = File::get($file);

            if (! str_contains($content, 'DB::transaction(')) {
                $issues[] = "app/Services/{$relative} lacks a transaction boundary.";
            }

            if (! str_contains($content, 'lockForUpdate()')) {
                $issues[] = "app/Services/{$relative} lacks a production row lock.";
            }
        }

        $withdrawals = File::get(app_path('Services/Owners/OwnerWithdrawalService.php'));
        if (str_contains($withdrawals, '$claimed->update([') || str_contains($withdrawals, '$claimed->refresh()->update([')) {
            $issues[] = 'Unlocked withdrawal state transition remains.';
        }

        $this->line('Controllers scanned: '.count(File::allFiles(app_path('Http/Controllers'))));
        $this->line('Services scanned: '.count(File::allFiles(app_path('Services'))));

        if ($issues !== []) {
            foreach (array_unique($issues) as $issue) {
                $this->error($issue);
            }

            return self::FAILURE;
        }

        $this->info('Phase 3B + 3C service and transaction audit passed.');

        return self::SUCCESS;
    }
}
