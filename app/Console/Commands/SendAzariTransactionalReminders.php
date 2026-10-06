<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Communications\AzariTransactionalMailService;
use Illuminate\Console\Command;

class SendAzariTransactionalReminders extends Command
{
    protected $signature = 'azari:send-transactional-reminders {--dry-run : Count eligible messages without queueing them}';
    protected $description = 'Queue idempotent arrival, check-in, extension, checkout and post-stay Azari emails.';

    public function handle(AzariTransactionalMailService $mail): int
    {
        $timezone = config('localization.platform_timezone', 'UTC');
        $today = now($timezone)->startOfDay();

        $counts = [
            'arrival' => 0,
            'check_in' => 0,
            'extension' => 0,
            'checkout' => 0,
            'post_stay' => 0,
        ];

        $active = ['confirmed', 'paid', 'check_in', 'checked_in'];

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', $active)
            ->whereDate('check_in', $today->copy()->addDay()->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    $counts['arrival']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendArrivalReminder($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereDate('check_in', $today->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    $counts['check_in']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendCheckInNotice($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', $active)
            ->whereDate('check_out', $today->copy()->addDays(2)->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    $counts['extension']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendExtensionReminder($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', $active)
            ->whereDate('check_out', $today->copy()->addDay()->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    $counts['checkout']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendCheckoutReminder($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', ['completed', 'checked_out'])
            ->where(function ($query) use ($today): void {
                $cutoff = $today->copy()->subDays(14);
                $query->where('completed_at', '>=', $cutoff)
                    ->orWhere('checked_out_at', '>=', $cutoff);
            })
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    $counts['post_stay']++;
                    if (! $this->option('dry-run')) {
                        $mail->stayCompleted($booking);
                    }
                }
            });

        foreach ($counts as $label => $count) {
            $this->line(str_replace('_', ' ', ucfirst($label)).': '.$count);
        }

        $this->info($this->option('dry-run')
            ? 'Dry run complete. No emails were queued.'
            : 'Transactional reminder pass complete.');

        return self::SUCCESS;
    }
}
