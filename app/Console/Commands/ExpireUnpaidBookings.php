<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Communications\AzariTransactionalMailService;
use Illuminate\Console\Command;

class ExpireUnpaidBookings extends Command
{
    protected $signature = 'azari:expire-unpaid-bookings {--dry-run : Count eligible bookings without cancelling them}';

    protected $description = 'Cancel expired unpaid booking holds and queue the branded expiry emails.';

    public function handle(AzariTransactionalMailService $mail): int
    {
        $cutoff = now();
        $eligible = $this->eligibleQuery($cutoff);

        if ($this->option('dry-run')) {
            $count = (clone $eligible)->count();
            $this->info('Expired unpaid bookings eligible for cancellation: '.$count);
            $this->info('Dry run complete. No bookings were changed and no emails were queued.');

            return self::SUCCESS;
        }

        $expired = 0;

        $eligible
            ->select(['bookings.id'])
            ->chunkById(100, function ($candidates) use ($cutoff, $mail, &$expired): void {
                foreach ($candidates as $candidate) {
                    $updated = $this->eligibleQuery($cutoff)
                        ->whereKey($candidate->getKey())
                        ->update([
                            'status' => 'cancelled',
                            'cancelled_at' => $cutoff,
                            'cancellation_reason' => 'Payment window expired',
                            'modified_at' => $cutoff,
                        ]);

                    if ($updated !== 1) {
                        continue;
                    }

                    $booking = Booking::query()
                        ->with(['property', 'user', 'payments'])
                        ->find($candidate->getKey());

                    if (! $booking) {
                        continue;
                    }

                    $mail->bookingCancelled($booking);
                    $expired++;
                }
            }, column: 'bookings.id', alias: 'id');

        $this->info('Expired unpaid bookings cancelled: '.$expired);
        $this->info('Branded booking-expired emails were queued idempotently for the affected guests.');

        return self::SUCCESS;
    }

    private function eligibleQuery($cutoff)
    {
        return Booking::query()
            ->whereIn('status', ['pending', 'pending_payment'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->whereDoesntHave('payments', function ($payments): void {
                $payments->where('status', Payment::SUCCESSFUL);
            });
    }
}
