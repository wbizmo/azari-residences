<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AzariBookingCancellationController extends Controller
{
    public function __invoke(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        abort_if(in_array($booking->status, ['cancelled', 'completed'], true), 422, 'This booking can no longer be cancelled.');
        $from = $booking->status;
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $data['reason']]);
        BookingStatusHistory::create(['booking_id' => $booking->id, 'changed_by' => $request->user()->id, 'from_status' => $from, 'to_status' => 'cancelled', 'note' => $data['reason']]);
        return back()->with('success', 'Booking cancelled by administrator.');
    }
}
