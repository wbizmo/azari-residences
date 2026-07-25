<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function search(Request $request, AvailabilityService $service)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1'],
        ]);

        $quote = $service->quote(
            (int) $data['property_id'],
            Carbon::parse($data['check_in']),
            Carbon::parse($data['check_out'])
        );

        return $request->expectsJson()
            ? response()->json($quote)
            : view('public.bookings.create', compact('data', 'quote'));
    }

    public function store(Request $request, AvailabilityService $service)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'guest_name' => ['required', 'string'],
            'guest_email' => ['required', 'email'],
            'guest_phone' => ['nullable', 'string'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['required', 'integer', 'min:1'],
            'guest_notes' => ['nullable', 'string'],
        ]);

        $quote = $service->quote(
            (int) $data['property_id'],
            Carbon::parse($data['check_in']),
            Carbon::parse($data['check_out'])
        );

        abort_unless($quote['available'], 422, 'Selected dates are unavailable.');

        $booking = Booking::create([
            ...$data,
            'user_id' => auth()->id(),
            'reference' => 'AZR-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'verification_status' => 'unverified',
            'currency' => $quote['currency'],
            'subtotal' => $quote['subtotal'],
            'tax_total' => 0,
            'total' => $quote['subtotal'],
        ]);

        return redirect()->route('bookings.show', $booking->reference);
    }

    public function show(string $reference)
    {
        return view('public.bookings.show', [
            'booking' => Booking::where('reference', $reference)->firstOrFail(),
        ]);
    }

    public function verify(Request $request)
    {
        $reference = Str::upper(trim((string) $request->input('reference', '')));
        $booking = null;

        if ($reference !== '') {
            $booking = Booking::where('reference', $reference)->first();
        }

        if ($request->expectsJson()) {
            if ($reference === '') {
                return response()->json([
                    'valid' => false,
                    'booking' => null,
                    'message' => 'A booking reference is required.',
                ], 422);
            }

            return response()->json([
                'valid' => (bool) $booking,
                'booking' => $booking ? [
                    'reference' => $booking->reference,
                    'status' => $booking->status,
                ] : null,
            ], $booking ? 200 : 404);
        }

        return view('public.verification.booking', compact('booking', 'reference'));
    }
}
