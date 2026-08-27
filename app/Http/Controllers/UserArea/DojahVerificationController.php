<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\BookingGuest;
use App\Services\Identity\DojahService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DojahVerificationController extends Controller
{
    public function user(Request $request, DojahService $dojah): View
    {
        $user = $request->user();
        $latest = $dojah->latestForUser($user);
        $verification = $latest?->isVerified() ? $latest : $dojah->verificationForUser($user);

        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
        $continueUrl = $verification->isVerified()
            ? $request->session()->pull('url.intended', route('user.dashboard'))
            : $request->session()->get('url.intended', route('user.dashboard'));

        return view('user.identity.dojah', [
            'verification' => $verification,
            'widgetConfigured' => $dojah->widgetConfigured(),
            'widget' => $dojah->widgetPayload($verification, [
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
                'email' => $user->email,
            ]),
            'subjectName' => $user->name,
            'backUrl' => route('user.dashboard'),
            'continueUrl' => $continueUrl,
        ]);
    }

    public function guest(Request $request, string $reference, BookingGuest $guest, DojahService $dojah): View
    {
        $booking = $request->user()->bookings()->where('reference', $reference)->firstOrFail();
        abort_unless($guest->booking_id === $booking->id && $guest->type === 'adult', 404);

        $latest = $dojah->latestForGuest($guest);
        $verification = $latest?->isVerified() ? $latest : $dojah->verificationForGuest($guest);

        return view('user.identity.dojah', [
            'verification' => $verification,
            'widgetConfigured' => $dojah->widgetConfigured(),
            'widget' => $dojah->widgetPayload($verification, [
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'email' => $booking->guest_email,
            ]),
            'subjectName' => $guest->full_name,
            'backUrl' => route('user.guests.index'),
            'continueUrl' => route('user.guests.index'),
        ]);
    }
}
