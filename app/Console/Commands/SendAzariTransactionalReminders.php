<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use App\Services\Communications\AzariTransactionalMailService;
use Illuminate\Console\Command;

class SendAzariTransactionalReminders extends Command
{
    protected $signature = 'azari:send-transactional-reminders {--dry-run : Count eligible messages without queueing them}';
    protected $description = 'Queue idempotent arrival, check-in, extension, checkout and post-stay Azari emails.';

    public function handle(AzariTransactionalMailService $mail): int
    {
        $timezone = config('localization.platform_timezone', 'UTC');
        $today = CarbonImmutable::now($timezone)->startOfDay();

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
            ->where('check_in', '>=', $today->subDay()->toDateString())
            ->where('check_in', '<', $today->addDays(3)->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    if ($booking->check_in?->toDateString() !== $this->localToday($booking)->addDay()->toDateString()) continue;
                    $counts['arrival']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendArrivalReminder($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', ['confirmed', 'paid'])
            ->where('check_in', '>=', $today->subDay()->toDateString())
            ->where('check_in', '<', $today->addDays(2)->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    if ($booking->check_in?->toDateString() !== $this->localToday($booking)->toDateString()) continue;
                    $counts['check_in']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendCheckInNotice($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', $active)
            ->where('check_out', '>=', $today->addDay()->toDateString())
            ->where('check_out', '<', $today->addDays(4)->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    if ($booking->check_out?->toDateString() !== $this->localToday($booking)->addDays(2)->toDateString()) continue;
                    $counts['extension']++;
                    if (! $this->option('dry-run')) {
                        $mail->sendExtensionReminder($booking);
                    }
                }
            });

        Booking::query()
            ->with(['property', 'user', 'payments'])
            ->whereIn('status', $active)
            ->where('check_out', '>=', $today->toDateString())
            ->where('check_out', '<', $today->addDays(3)->toDateString())
            ->chunkById(100, function ($bookings) use (&$counts, $mail): void {
                foreach ($bookings as $booking) {
                    if ($booking->check_out?->toDateString() !== $this->localToday($booking)->addDay()->toDateString()) continue;
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
    private function localToday(Booking $booking): CarbonImmutable
    {
        // Preserve the booking's captured property timezone even when the
        // listing's location or the platform default is updated later.
        $zone = LocalDate::timezone(
            $booking->property_timezone
                ?: ($booking->property ? LocalDate::propertyTimezone($booking->property) : null)
        );

        return CarbonImmutable::now($zone)->startOfDay();
    }

}
