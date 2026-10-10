<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('azari:sync-bookings')->hourly()->withoutOverlapping();
Schedule::command('azari:reconcile-payments --limit=100')->everyTenMinutes()->withoutOverlapping();
Schedule::command('resavar:reconcile-provider-refunds --limit=50')->everyTenMinutes()->name('provider-refunds')->withoutOverlapping();
Schedule::command('resavar:reconcile-cancellation-refunds --limit=100')->everyTenMinutes()->name('cancellation-refund-recovery')->withoutOverlapping();
Schedule::command('resavar:recover-amendment-payments --limit=50')->everyFiveMinutes()->name('expired-amendments')->withoutOverlapping();
Schedule::command('azari:send-transactional-reminders')->hourly()->withoutOverlapping();
Schedule::command('resavar:escalate-overdue-support --limit=50')->everyFiveMinutes()->name('critical-support-escalation')->withoutOverlapping();
Schedule::command('resavar:deliver-booking-message-alerts --limit=100')->everyMinute()->name('booking-message-alert-outbox')->withoutOverlapping();
Schedule::command('resavar:scan-booking-attachments --limit=25')->everyMinute()->name('booking-attachment-scanner')->withoutOverlapping();
Schedule::command('resavar:purge-booking-attachments --limit=200')->dailyAt('03:50')->name('booking-attachment-retention')->withoutOverlapping();
Schedule::command('azari:send-unpaid-booking-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('azari:provision-successful-booking-accounts')->hourly()->withoutOverlapping();
if (config('filesystems.media_backup_schedule_enabled', false)) {
    Schedule::command('resavar:backup-private-media')
        ->dailyAt('03:10')->name('offsite-private-media-backup')->withoutOverlapping();
}
Schedule::command('model:prune')->daily();
Schedule::command('azari:expire-unpaid-bookings')->everyFiveMinutes()->name('expire-unpaid-bookings')->withoutOverlapping();
Schedule::command('azari:sitemap')->dailyAt('04:15')->name('public-sitemap')->withoutOverlapping();
Schedule::command('azari:heartbeat')->everyMinute()->name('system-heartbeat')->withoutOverlapping();
Schedule::command('azari:sync-channels')->everyTenMinutes()->name('channel-sync')->withoutOverlapping();
if (config('travel.dining_enabled',false)) {
    Schedule::command('resavar:dining-arrival-reminders --limit=50')
        ->everyTenMinutes()->name('dining-arrival-reminders')->withoutOverlapping();
}
if (config('travel.webhooks_enabled', false)) {
    Schedule::command('resavar:process-travel-webhooks --limit=50')
        ->everyMinute()->name('travel-supplier-event-inbox')->withoutOverlapping();
}
Schedule::command('resavar:purge-unverified-accounts')->dailyAt('03:35')->name('purge-unverified-accounts')->withoutOverlapping();


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
