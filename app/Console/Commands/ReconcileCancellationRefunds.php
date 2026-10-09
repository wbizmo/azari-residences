<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Bookings\BookingCancellationSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileCancellationRefunds extends Command
{
    protected $signature = 'resavar:reconcile-cancellation-refunds {--limit=100 : Maximum number of terminal bookings checked}';
    protected $description = 'Repair missing policy refund requests; never asserts that a provider has paid out.';

    public function handle(BookingCancellationSettlementService $service): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $result = ['checked' => 0, 'new_requests' => 0, 'errors' => 0];
        Booking::query()->whereIn('status', ['cancelled', 'no_show'])
            ->where(function ($query): void {
                $query->where('cancelled_at', '>=', now()->subDays(30))
                    ->orWhere('no_show_at', '>=', now()->subDays(30));
            })
            ->orderByDesc('id')->limit($limit)->get()
            ->each(function (Booking $booking) use ($service, &$result): void {
                $result['checked']++;
                try {
                    $reserved = $service->reserveEligibleRefunds($booking);
                    $result['new_requests'] += $reserved['refunds_requested'];
                } catch (\Throwable $error) {
                    $result['errors']++;
                    Log::warning('Cancellation policy refund requires attention.', [
                        'booking_id' => $booking->getKey(), 'failure_class' => $error::class,
                    ]);
                }
            });
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
