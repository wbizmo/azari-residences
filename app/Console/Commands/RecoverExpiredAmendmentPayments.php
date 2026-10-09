<?php

namespace App\Console\Commands;

use App\Models\BookingModificationRequest;
use App\Models\Payment;
use App\Services\Bookings\ExpiredAmendmentRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class RecoverExpiredAmendmentPayments extends Command
{
    protected $signature = 'resavar:recover-amendment-payments {--limit=50 : Maximum 1-250 expired quotes to check}';
    protected $description = 'Expire stale date-change offers and reserve refunds for verified, unallocated topups.';

    public function handle(ExpiredAmendmentRecoveryService $service): int
    {
        $limit = min(250, max(1, (int) $this->option('limit')));
        $counts = ['checked' => 0, 'refund_requested' => 0, 'error' => 0];

        BookingModificationRequest::query()
            ->where('type', 'date_change')
            ->whereNotNull('quote_expires_at')
            ->where('quote_expires_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->where('status', 'quoted')
                    ->orWhere(function (Builder $retry): void {
                        $retry->where('status', 'expired')
                            ->whereIn('payment_id', Payment::query()
                                ->select('id')
                                ->where('payment_kind', 'amendment')
                                ->where('status', 'successful_excess')
                                ->whereNotNull('verified_at'));
                    });
            })
            ->orderBy('id')->limit($limit)->get()
            ->each(function (BookingModificationRequest $request) use ($service, &$counts): void {
                $counts['checked']++;
                try {
                    if ($service->requestRecovery($request)) {
                        $counts['refund_requested']++;
                    }
                } catch (\Throwable $exception) {
                    $counts['error']++;
                    Log::warning('Expired amendment money requires operator reconciliation.', [
                        'modification_id' => $request->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            });

        $this->line(json_encode($counts, JSON_THROW_ON_ERROR));

        return $counts['error'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
