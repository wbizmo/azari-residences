<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcileAzariPayments extends Command
{
    protected $signature = 'azari:reconcile-payments {--limit=100 : Maximum records to check}';
    protected $description = 'Re-query unresolved Flutterwave, Pesapal and InTouch payments and apply verified results idempotently.';

    public function handle(PaymentReconciliationService $reconciliation): int
    {
        $result = $reconciliation->reconcilePending((int) $this->option('limit'));
        $this->info("Checked: {$result['checked']}; successful: {$result['successful']}; pending: {$result['pending']}; failed: {$result['failed']}; stale abandoned: {$result['abandoned']}");
        return self::SUCCESS;
    }
}
