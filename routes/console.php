<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('azari:sync-bookings')->hourly()->withoutOverlapping();
Schedule::command('azari:reconcile-payments --limit=100')->everyTenMinutes()->withoutOverlapping();
Schedule::command('azari:send-transactional-reminders')->hourly()->withoutOverlapping();
Schedule::command('model:prune')->daily();
Schedule::command('azari:expire-unpaid-bookings')->hourly()->name('expire-unpaid-bookings')->withoutOverlapping();

require __DIR__.'/azari-final-schedule.php';
