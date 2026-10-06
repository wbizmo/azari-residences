<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = today();

        $base = $user->bookings()->with([
            'property',
            'accommodationType',
            'ratePlan',
            'payments',
        ]);

        $currentStay = (clone $base)
            ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->orderBy('check_out')
            ->first();

        $nextBooking = (clone $base)
            ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
            ->whereDate('check_in', '>', $today)
            ->orderBy('check_in')
            ->first();

        $tripCounts = [
            'current' => $user->bookings()
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->whereDate('check_in', '<=', $today)
                ->whereDate('check_out', '>=', $today)
                ->count(),
            'upcoming' => $user->bookings()
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->whereDate('check_in', '>', $today)
                ->count(),
            'past' => $user->bookings()
                ->where(function ($query) use ($today): void {
                    $query->whereIn('status', ['completed', 'checked_out', 'no_show'])
                        ->orWhereDate('check_out', '<', $today);
                })
                ->where('status', '!=', 'cancelled')
                ->count(),
            'cancelled' => $user->bookings()->where('status', 'cancelled')->count(),
        ];

        return view('user.dashboard', [
            'nextBooking' => $nextBooking,
            'currentStay' => $currentStay,
            'tripCounts' => $tripCounts,
            'upcomingCount' => $tripCounts['upcoming'],
            'pendingPaymentCount' => $user->bookings()
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->get()
                ->filter(fn ($booking) => $booking->balanceDue() > 0)
                ->count(),
            'recentPayments' => $user->payments()
                ->with('booking.property')
                ->where('status', Payment::SUCCESSFUL)
                ->latest('paid_at')
                ->limit(3)
                ->get(),
            'favourites' => $user->favourites()
                ->with('property.locationRecord')
                ->latest()
                ->limit(6)
                ->get(),
            'savedSearches' => $user->savedSearches()
                ->latest('last_used_at')
                ->limit(5)
                ->get(),
            'recentlyViewed' => $user->recentlyViewedProperties()
                ->with('property.locationRecord')
                ->latest('viewed_at')
                ->limit(6)
                ->get(),
            'pendingModifications' => $user->bookingModificationRequests()
                ->with('booking.property')
                ->where('status', 'pending')
                ->latest()
                ->limit(5)
                ->get(),
            'identityVerified' => $user->hasVerifiedIdentity(),
            'openServiceRequests' => $user->serviceRequests()->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
            'openSupportTickets' => $user->supportTickets()->whereNotIn('status', ['resolved', 'closed'])->count(),
        ]);
    }
}
