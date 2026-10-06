<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\IdentityVerification;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Bookings\BookingCreationService;
use Illuminate\Http\Request;

class AzariBookingFlowController extends Controller
{
    public function checkout(Request $request, string $token, AzariPricingEngine $pricing)
    {
        $hold = BookingHold::query()
            ->with(['property', 'accommodationType', 'ratePlan.cancellationPolicy', 'ratePlan.paymentPolicy'])
            ->active()
            ->where('token', $token)
            ->firstOrFail();

        abort_unless(
            $request->user() && IdentityVerification::userIsVerified((int) $request->user()->id),
            403,
            'Complete Dojah identity verification before booking.'
        );

        if ($hold->user_id !== null) {
            abort_unless((int) $hold->user_id === (int) $request->user()->id, 403);
        }

        return view('public.bookings.checkout', [
            'hold' => $hold,
            'quote' => $pricing->quote(
                $hold->property,
                $hold->check_in,
                $hold->check_out,
                [],
                $hold->accommodationType,
                $hold->ratePlan,
                max(1, (int) $hold->rooms)
            ),
        ]);
    }

    public function store(Request $request, BookingCreationService $bookings)
    {
        $booking = $bookings->create($request);

        $request->session()->put('azari_guest_bookings.'.$booking->reference, true);

        return redirect()
            ->route('azari.booking.review', $booking->reference)
            ->with('success', 'Guest details saved. Every adult must complete Dojah verification before payment.');
    }

    public function review(Request $request, string $reference)
    {
        $booking = Booking::query()
            ->with(['property', 'guests.latestIdentityVerification'])
            ->where('reference', $reference)
            ->firstOrFail();

        $this->authorizeBooking($request, $booking);

        return view('public.bookings.review', compact('booking'));
    }

    public function confirm(Request $request, string $reference)
    {
        $booking = Booking::query()
            ->with('guests.latestIdentityVerification')
            ->where('reference', $reference)
            ->firstOrFail();

        $this->authorizeBooking($request, $booking);
        abort_unless(in_array($booking->status, ['pending', 'pending_payment'], true), 422);

        $unverifiedAdults = $booking->guests
            ->where('type', 'adult')
            ->filter(fn ($guest) => ! $guest->latestIdentityVerification?->isVerified());

        if ($unverifiedAdults->isNotEmpty()) {
            return redirect()
                ->route('user.guests.index')
                ->with('warning', 'Every adult on this booking must complete Dojah identity verification before payment.');
        }

        return redirect()->route('public.payment.select', $booking->reference);
    }

    public function summary(Request $request, string $reference)
    {
        $booking = Booking::query()->with(['property', 'guests.latestIdentityVerification', 'payments'])->where('reference', $reference)->firstOrFail();
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
