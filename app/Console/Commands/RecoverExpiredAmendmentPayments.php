<?php

namespace App\Console\Commands;

use App\Models\BookingModificationRequest;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\RefundService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverExpiredAmendmentPayments extends Command
{
    protected $signature = 'resavar:recover-amendment-payments {--limit=50 : Maximum 1-250 expired quotes to check}';
    protected $description = 'Close expired amendments and request idempotent refunds of verified, unallocated topups.';

    public function handle(RefundService $refunds): int
    {
        $limit = min(250, max(1, (int) $this->option('limit')));
        $counts = ['checked' => 0, 'expired' => 0, 'refund_requested' => 0, 'error' => 0];

        BookingModificationRequest::query()
            // Do not starve newer work by repeatedly scanning historical
            // expired offers with no verified unallocated payment.
            ->where(function (Builder $query): void {
                $query->where('status', 'quoted')
                    ->orWhere(function (Builder $expired): void {
                        $expired->where('status', 'expired')
                            ->whereIn('payment_id', Payment::query()
                                ->select('id')
                                ->where('status', 'successful_excess')
                                ->whereNotNull('verified_at'));
                    });
            })
            ->whereNotNull('quote_expires_at')
            ->where('quote_expires_at', '<', now())
            ->orderBy('id')->limit($limit)->get()
            ->each(function (BookingModificationRequest $request) use ($refunds, &$counts): void {
                $counts['checked']++;
                try {
                    DB::transaction(function () use ($request, &$counts): void {
                        $locked = BookingModificationRequest::query()
                            ->whereKey($request->getKey())
                            ->when(DB::connection()->getDriverName() !== 'sqlite',
                                fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
                        if ($locked->status === 'quoted' && $locked->quote_expires_at?->isPast()) {
                            $locked->forceFill(['status' => 'expired'])->save();
                            $counts['expired']++;
                        }
                    }, 5);

                    $expired = $request->fresh();
                    if ($expired->status !== 'expired' || ! $expired->payment_id) {
                        return;
                    }
                    $payment = Payment::query()->whereKey($expired->payment_id)->first();
                    if (! $payment || $payment->payment_kind !== 'amendment'
                        || $payment->status !== 'successful_excess' || ! $payment->verified_at) {
                        return;
                    }

                    $key = hash('sha256', 'expired-amendment|'.$expired->getKey().'|'.$payment->getKey());
                    if (Refund::query()->where('idempotency_key', $key)->exists()) {
                        return;
                    }
                    // The entire topup was never applied to the original booking,
                    // so its full amount is owed back to the original payee.
                    $refunds->request($payment, (float) $payment->amount, null,
                        'Automatic refund request: amendment offer expired without allocation.', $key);
                    $counts['refund_requested']++;
                } catch (\Throwable $exception) {
                    $counts['error']++;
                    Log::warning('Expired amendment topup needs reconciliation.', [
                        'modification_id' => $request->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            });

        $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
        return $counts['error'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
