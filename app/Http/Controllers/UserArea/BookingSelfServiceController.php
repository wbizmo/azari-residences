<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\BookingModificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingSelfServiceController extends Controller
{
    public function storeModification(
        Request $request,
        string $reference,
        BookingModificationService $modifications
    ): RedirectResponse {
        $booking = $request->user()->bookings()
            ->where('reference', $reference)
            ->first();

        abort_unless($booking, 404);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'guest_note' => ['nullable', 'string', 'max:2000'],
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'adult_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'child_count' => ['nullable', 'integer', 'min:0', 'max:8'],
            'room_preference' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'add_on_ids' => ['nullable', 'array', 'max:20'],
            'add_on_ids.*' => ['integer', 'min:1'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $type = $data['type'];
        $note = $data['guest_note'] ?? null;
        unset($data['type'], $data['guest_note']);

        $modifications->request($booking, $request->user(), $type, $data, $note);

        return back()->with('success', 'Your change request was submitted.');
    }
}
