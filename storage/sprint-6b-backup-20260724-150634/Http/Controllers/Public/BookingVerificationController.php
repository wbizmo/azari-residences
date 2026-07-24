<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingVerificationController extends Controller
{
    public function form()
    {
        return view('public.verification.index');
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['booking_number' => ['required', 'string', 'max:100']]);

        $booking = DB::table('bookings')
            ->where('booking_number', $data['booking_number'])
            ->first();

        return view('public.verification.index', [
            'searched' => true,
            'booking' => $booking,
        ]);
    }

    public function qr(string $bookingNumber)
    {
        $booking = DB::table('bookings')->where('booking_number', $bookingNumber)->first();
        abort_unless($booking, 404);

        return view('public.verification.result', compact('booking'));
    }
}
