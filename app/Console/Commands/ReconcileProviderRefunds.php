<?php

namespace App\Console\Commands;

use App\Models\Refund;
use App\Services\Payments\ProviderRefundExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileProviderRefunds extends Command
{
    protected $signature = 'resavar:reconcile-provider-refunds {--limit=50 : Max 1-250 pending provider refunds}';
    protected $description = 'Poll provider refund receipts; only verified settlement marks a refund successful.';

    public function handle(ProviderRefundExecutionService $executor): int
    {
        $limit = max(1, min(250, (int) $this->option('limit')));
        $result = ['checked' => 0, 'settled' => 0, 'pending' => 0, 'failed' => 0];

        Refund::query()
            ->where('provider', 'flutterwave')
            ->whereIn('status', ['processing', 'reconciliation_required'])
            ->whereNotNull('provider_reference')
            ->orderBy('updated_at')->orderBy('id')->limit($limit)
            ->get()
            ->each(function (Refund $refund) use ($executor, &$result): void {
                $result['checked']++;
                try {
                    $verified = $executor->reconcile($refund);
                    if ($verified->status === 'successful') {
                        $result['settled']++;
                    } elseif ($verified->status === 'failed') {
                        $result['failed']++;
                    } else {
                        $result['pending']++;
                    }
                } catch (\Throwable $exception) {
                    $result['failed']++;
                    Log::warning('Provider refund reconciliation requires operator attention.', [
                        'refund_id' => $refund->getKey(),
                        'provider' => $refund->provider,
                        'failure_class' => $exception::class,
                    ]);
                }
            });

        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
