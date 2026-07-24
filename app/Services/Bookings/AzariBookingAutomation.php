<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingStatusHistory;

class AzariBookingAutomation
{
    public function run(): array
    {
        $today = today();
        $checkIns = 0;
        $completed = 0;

        Booking::query()->where('status', 'paid')->whereDate('check_in', '<=', $today)
            ->chunkById(100, function ($bookings) use (&$checkIns): void {
                foreach ($bookings as $booking) {
                    $booking->update(['status' => 'check_in', 'checked_in_at' => now()]);
                    BookingStatusHistory::create(['booking_id' => $booking->id, 'from_status' => 'paid', 'to_status' => 'check_in', 'note' => 'Automatically opened for check-in on arrival date.']);
                    $checkIns++;
                }
            });

        Booking::query()->whereIn('status', ['paid', 'check_in'])->whereDate('check_out', '<', $today)
            ->chunkById(100, function ($bookings) use (&$completed): void {
                foreach ($bookings as $booking) {
                    $from = $booking->status;
                    $booking->update(['status' => 'completed', 'completed_at' => now()]);
                    BookingStatusHistory::create(['booking_id' => $booking->id, 'from_status' => $from, 'to_status' => 'completed', 'note' => 'Automatically completed after departure date.']);
                    $completed++;
                }
            });

        return compact('checkIns', 'completed');
    }
}
