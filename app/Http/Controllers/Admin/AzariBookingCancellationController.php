<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AzariBookingCancellationController extends Controller
{
    public function __invoke(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
            'payment_status_note' => ['nullable', 'string', 'max:2000'],
            'external_refund_reference' => ['nullable', 'string', 'max:190'],
        ]);
        abort_if(in_array($booking->status, ['cancelled', 'completed', 'checked_out'], true), 422, 'This booking can no longer be cancelled.');
        $from = $booking->status;
        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancellation_reason' => $data['reason'],
            'cancellation_internal_note' => $data['internal_note'] ?? null,
            'cancellation_payment_note' => $data['payment_status_note'] ?? null,
            'external_refund_reference' => $data['external_refund_reference'] ?? null,
        ]);
        BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'changed_by' => $request->user()->id,
            'from_status' => $from,
            'to_status' => 'cancelled',
            'note' => $data['reason'],
            'metadata' => [
                'payment_status_note' => $data['payment_status_note'] ?? null,
                'external_refund_reference' => $data['external_refund_reference'] ?? null,
                'refund_handled_externally' => filled($data['external_refund_reference'] ?? null),
            ],
        ]);
        AuditLog::record('booking.cancelled', $booking, ['status' => $from], ['status' => 'cancelled'], ['reason' => $data['reason']]);
        return back()->with('success', 'Booking cancelled. Any refund remains external to this platform.');
    }
}
