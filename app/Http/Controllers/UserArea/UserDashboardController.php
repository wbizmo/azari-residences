<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $base = $user->bookings()->with(['property', 'payments']);
        $nextBooking = (clone $base)->whereIn('status', ['confirmed', 'paid', 'check_in', 'checked_in'])
            ->whereDate('check_out', '>=', today())->orderBy('check_in')->first();
        $currentStay = (clone $base)->whereIn('status', ['check_in', 'checked_in'])
            ->whereDate('check_in', '<=', today())->whereDate('check_out', '>=', today())->first();

        return view('user.dashboard', [
            'nextBooking' => $nextBooking,
            'currentStay' => $currentStay,
            'upcomingCount' => $user->bookings()->whereDate('check_in', '>=', today())->whereNotIn('status', ['cancelled', 'completed'])->count(),
            'pendingPaymentCount' => $user->bookings()->whereIn('status', ['pending', 'pending_payment'])->count(),
            'recentPayments' => $user->payments()->with('booking.property')->where('status', Payment::SUCCESSFUL)->latest('paid_at')->limit(3)->get(),
            'openServiceRequests' => 0,
            'openSupportTickets' => 0,
        ]);
    }
}
