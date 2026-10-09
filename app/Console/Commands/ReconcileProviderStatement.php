<?php

namespace App\Console\Commands;

use App\Services\Payments\ProviderStatementReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileProviderStatement extends Command
{
    protected $signature = 'resavar:reconcile-provider-statement
        {provider : The configured provider identifier}
        {csv : Local CSV path with normalized provider settlement evidence}
        {--json : Machine-readable JSON output}';

    protected $description = 'Compare external settlement CSV against local records without creating or updating payments.';

    public function handle(ProviderStatementReconciliationService $service): int
    {
        try {
            $result = $service->compare(
                (string) $this->argument('provider'),
                (string) $this->argument('csv')
            );
            $this->line(json_encode($result, $this->option('json')
                ? JSON_THROW_ON_ERROR
                : JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return $result['statement_reconciled'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            Log::warning('Provider reconciliation import rejected.', [
                'exception_class' => $e::class,
            ]);
            $this->error('Statement invalid or inaccessible. Verify its format and secure file location.');
            return self::FAILURE;
        }
    }
}
