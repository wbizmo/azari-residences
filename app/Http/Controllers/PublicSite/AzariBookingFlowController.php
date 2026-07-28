<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingCreationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AzariBookingFlowController extends Controller
{
    public function checkout(Request $request, string $token, AzariPricingEngine $pricing)
    {
        $hold = BookingHold::query()->with('property')->active()->where('token', $token)->firstOrFail();
        return view('public.bookings.checkout', [
            'hold' => $hold,
            'quote' => $pricing->quote($hold->property, $hold->check_in, $hold->check_out),
            'accountIdentity' => $request->user()?->currentIdentity()->with('identityType')->first(),
        ]);
    }

    public function store(Request $request, BookingCreationService $bookings)
    {
        $booking = $bookings->create($request);

        $request->session()->put('azari_guest_bookings.'.$booking->reference, true);
        return redirect()->route('azari.booking.review', $booking->reference)->with('success', 'Guest details saved. Review the booking before payment.');
    }

    public function review(Request $request, string $reference)
    {
        $booking = Booking::query()->with(['property', 'guests.identityDocument', 'guests.identityLink.userIdentityDocument.identityType'])->where('reference', $reference)->firstOrFail();
        $this->authorizeBooking($request, $booking);
        return view('public.bookings.review', compact('booking'));
    }

    public function confirm(Request $request, string $reference)
    {
        $booking = Booking::query()->where('reference', $reference)->firstOrFail();
        $this->authorizeBooking($request, $booking);
        abort_unless(in_array($booking->status, ['pending', 'pending_payment'], true), 422);
        return redirect()->route('public.payment.select', $booking->reference);
    }

    public function summary(Request $request, string $reference)
    {
        $booking = Booking::query()->with(['property', 'guests', 'payments'])->where('reference', $reference)->firstOrFail();
        $this->authorizeBooking($request, $booking);
        return view('public.bookings.summary', compact('booking'));
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        if ($request->user()) {
            abort_unless(! $request->user()->isStaff() && $booking->user_id === $request->user()->id, 403);
            return;
        }
        abort_unless((bool) $request->session()->get('azari_guest_bookings.'.$booking->reference, false), 403);
    }
}
