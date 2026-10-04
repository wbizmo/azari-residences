<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('azari:sync-bookings')->hourly()->withoutOverlapping();
Schedule::command('azari:reconcile-payments --limit=100')->everyTenMinutes()->withoutOverlapping();
Schedule::command('azari:send-transactional-reminders')->hourly()->withoutOverlapping();
Schedule::command('azari:send-unpaid-booking-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('azari:provision-successful-booking-accounts')->hourly()->withoutOverlapping();
Schedule::command('model:prune')->daily();
Schedule::command('azari:expire-unpaid-bookings')->everyFiveMinutes()->name('expire-unpaid-bookings')->withoutOverlapping();
Schedule::command('azari:sitemap')->dailyAt('04:15')->name('public-sitemap')->withoutOverlapping();

require __DIR__.'/azari-final-schedule.php';

/*
|--------------------------------------------------------------------------
| AZARI_PUBLIC_STATUS_SCHEDULE_V1
|--------------------------------------------------------------------------
|
| The production server already invokes Laravel's scheduler every minute.
| Generate one sanitized public snapshot every five minutes.
|
*/

\Illuminate\Support\Facades\Schedule::command(
    'azari:status-snapshot'
)->everyFiveMinutes();
