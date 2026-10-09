<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AzariBookingCancellationController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        BookingCancellationService $cancellations
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
            'payment_status_note' => ['nullable', 'string', 'max:2000'],
            'external_refund_reference' => ['nullable', 'string', 'max:190'],
        ]);

        $cancellations->cancel(
            $booking,
            $request->user()?->getKey(),
            $data['reason'],
            $data['internal_note'] ?? null,
            $data['payment_status_note'] ?? null,
            $data['external_refund_reference'] ?? null
        );

        return back()->with('success',
            'Booking cancelled and availability released. Any due refund is a separate tracked financial action.');
    }
}
