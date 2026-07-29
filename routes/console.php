<?php

use App\Models\Booking;
use Illuminate\Support\Facades\Schedule;

Schedule::command('azari:sync-bookings')->hourly()->withoutOverlapping();
Schedule::command('azari:reconcile-payments --limit=100')->everyTenMinutes()->withoutOverlapping();
Schedule::command('model:prune')->daily();
Schedule::call(function (): void {
    Booking::query()
        ->whereIn('status', ['pending', 'pending_payment'])
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Payment window expired',
            'modified_at' => now(),
        ]);
})->hourly()->name('expire-unpaid-bookings')->withoutOverlapping();

require __DIR__.'/azari-final-schedule.php';
