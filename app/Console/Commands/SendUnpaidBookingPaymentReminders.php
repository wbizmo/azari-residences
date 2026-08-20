<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\UnpaidBookingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendUnpaidBookingPaymentReminders extends Command
{
    protected $signature =
        'azari:send-unpaid-booking-reminders '
        .'{--dry-run : Count eligible reminders without queueing mail}';

    protected $description =
        'Send one payment reminder when an unpaid Azari booking reaches its reminder threshold.';

    public function handle(): int
    {
        $reminderMinutes = max(
            1,
            (int) config(
                'azari.booking.payment_reminder_minutes',
                30
            )
        );

        $now = now();

        $eligible = Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn(
                'status',
                [
                    'pending',
                    'pending_payment',
                ]
            )
            ->whereNull('payment_reminder_sent_at')
            ->whereNotNull('expires_at')
            ->where(
                'created_at',
                '<=',
                $now->copy()->subMinutes(
                    $reminderMinutes
                )
            )
            ->where(
                'expires_at',
                '>',
                $now
            )
            ->whereDoesntHave(
                'payments',
                function ($payments): void {
                    $payments->where(
                        'status',
                        Payment::SUCCESSFUL
                    );
                }
            );

        if ($this->option('dry-run')) {
            $this->info(
                'Eligible unpaid booking reminders: '
                .$eligible->count()
            );

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        $eligible
            ->select(['bookings.*'])
            ->chunkById(
                100,
                function ($bookings) use (
                    &$sent,
                    &$failed
                ): void {
                    foreach ($bookings as $booking) {
                        if (
                            blank(
                                $booking->guest_email
                            )
                        ) {
                            continue;
                        }

                        /*
                         * Re-check the row immediately before queueing so
                         * overlapping scheduler workers cannot both send it.
                         */
                        $fresh = Booking::query()
                            ->whereKey(
                                $booking->getKey()
                            )
                            ->whereNull(
                                'payment_reminder_sent_at'
                            )
                            ->whereIn(
                                'status',
                                [
                                    'pending',
                                    'pending_payment',
                                ]
                            )
                            ->where(
                                'expires_at',
                                '>',
                                now()
                            )
                            ->whereDoesntHave(
                                'payments',
                                function ($payments): void {
                                    $payments->where(
                                        'status',
                                        Payment::SUCCESSFUL
                                    );
                                }
                            )
                            ->first();

                        if (! $fresh) {
                            continue;
                        }

                        try {
                            $minutesRemaining = max(
                                1,
                                (int) now()->diffInMinutes(
                                    $fresh->expires_at,
                                    false
                                )
                            );

                            Notification::route(
                                'mail',
                                $fresh->guest_email
                            )->notify(
                                new UnpaidBookingReminderNotification(
                                    $fresh->reference,
                                    route(
                                        'public.payment.select',
                                        [
                                            $fresh->reference,
                                        ]
                                    ),
                                    $fresh->expires_at
                                        ->timezone(
                                            config(
                                                'azari.timezone',
                                                'Africa/Lagos'
                                            )
                                        )
                                        ->format(
                                            'j M Y, g:i A'
                                        ),
                                    $minutesRemaining
                                )
                            );

                            /*
                             * Mark only after the notification was
                             * successfully dispatched to the queue.
                             */
                            $updated = Booking::query()
                                ->whereKey(
                                    $fresh->getKey()
                                )
                                ->whereNull(
                                    'payment_reminder_sent_at'
                                )
                                ->update([
                                    'payment_reminder_sent_at' =>
                                        now(),
                                ]);

                            if ($updated === 1) {
                                $sent++;
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
            'Payment reminders queued: '.$sent
        );

        if ($failed > 0) {
            $this->warn(
                'Payment reminders that failed to queue: '
                .$failed
            );
        }

        return self::SUCCESS;
    }
}
