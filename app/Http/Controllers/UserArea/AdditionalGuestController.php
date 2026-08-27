<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\BookingGuest;
use App\Services\Identity\GuestVerificationInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdditionalGuestController extends Controller
{
    public function index(Request $request): View
    {
        $guests = BookingGuest::query()
            ->with([
                'booking.property',
                'latestIdentityVerification',
                'verificationInvite',
                'user',
            ])
            ->whereHas(
                'booking',
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->where('type', 'adult')
            ->where('is_lead', false)
            ->latest()
            ->paginate(10);

        return view('user.guests.index', compact('guests'));
    }

    public function sendInvite(
        Request $request,
        string $reference,
        BookingGuest $guest,
        GuestVerificationInvitationService $invitations
    ): RedirectResponse {
        $booking = $request->user()
            ->bookings()
            ->where('reference', $reference)
            ->firstOrFail();

        abort_unless(
            $guest->booking_id === $booking->id
            && $guest->type === 'adult'
            && ! $guest->is_lead,
            404
        );

        $invitations->sendInvite(
            $guest,
            $request->getSchemeAndHttpHost()
        );

        return back()->with(
            'success',
            'Verification invitation sent to '.$guest->email.'.'
        );
    }
}
