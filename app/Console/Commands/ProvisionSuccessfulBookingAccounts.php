<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Bookings\SuccessfulBookingAccountService;
use Illuminate\Console\Command;
use Throwable;

class ProvisionSuccessfulBookingAccounts extends Command
{
    protected $signature =
        'azari:provision-successful-booking-accounts '
        .'{--dry-run : Count successful guest bookings without provisioning accounts}';

    protected $description =
        'Provision or link customer accounts for successful guest bookings.';

    public function handle(
        SuccessfulBookingAccountService $accounts
    ): int {
        $query = Booking::query()
            ->whereNull('user_id')
            ->whereNotNull(
                'guest_email'
            )
            ->whereIn(
                'status',
                [
                    'paid',
                    'confirmed',
                    'check_in',
                    'checked_in',
                    'checked_out',
                    'completed',
                ]
            )
            ->orderBy('id');

        if ($this->option('dry-run')) {
            $eligible = 0;

            (clone $query)
                ->chunkById(
                    100,
                    function ($bookings) use (
                        &$eligible
                    ): void {
                        foreach ($bookings as $booking) {
                            if ($booking->isPaid()) {
                                $eligible++;
                            }
                        }
                    }
                );

            $this->info(
                'Successful guest bookings eligible for account linking: '
                .$eligible
            );

            return self::SUCCESS;
        }

        $linked = 0;
        $created = 0;
        $failed = 0;

        $query->chunkById(
            100,
            function ($bookings) use (
                $accounts,
                &$linked,
                &$created,
                &$failed
            ): void {
                foreach ($bookings as $booking) {
                    if (! $booking->isPaid()) {
                        continue;
                    }

                    try {
                        $result =
                            $accounts->provision(
                                $booking
                            );

                        $linked++;

                        if ($result['created']) {
                            $created++;

                            $accounts->sendActivation(
                                $result['user'],
                                $result['booking']
                            );
                        }
                    } catch (Throwable $exception) {
                        report(
                            $exception
                        );

                        $failed++;
                    }
                }
            }
        );

        $this->info(
            'Successful bookings linked to accounts: '
            .$linked
        );

        $this->info(
            'New customer accounts created: '
            .$created
        );

        if ($failed > 0) {
            $this->warn(
                'Account provisioning failures: '
                .$failed
            );
        }

        return self::SUCCESS;
    }
}
