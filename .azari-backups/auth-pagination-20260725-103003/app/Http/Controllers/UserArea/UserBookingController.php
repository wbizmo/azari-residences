<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserBookingController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $query = $request->user()->bookings()->with(['property', 'payments']);

        match ($status) {
            'upcoming' => $query->whereDate('check_in', '>=', today())->whereNotIn('status', ['cancelled', 'completed']),
            'active' => $query->whereIn('status', ['check_in', 'checked_in']),
            'completed' => $query->whereIn('status', ['completed', 'checked_out']),
            'cancelled' => $query->where('status', 'cancelled'),
            'pending-payment' => $query->whereIn('status', ['pending', 'pending_payment']),
            default => null,
        };

        $bookings = $query->latest()->paginate(10)->withQueryString();

        return view('user.bookings.index', compact('bookings', 'status'));
    }

    public function show(Request $request, string $reference): View
    {
        $booking = $request->user()->bookings()
            ->with(['property', 'guests.identityDocument', 'guests.identityLink.userIdentityDocument.identityType', 'identityLinks', 'payments'])
            ->withCount('payments')
            ->where('reference', $reference)
            ->firstOrFail();

        $timeline = $booking->statusHistory()->paginate(10, ['*'], 'timeline')->withQueryString();

        return view('user.bookings.show', compact('booking', 'timeline'));
    }

    public function receipt(Request $request, string $reference): Response
    {
        $booking = $request->user()->bookings()
            ->with(['property', 'payments'])
            ->where('reference', $reference)
            ->firstOrFail();

        $payment = $booking->payments->sortByDesc(fn ($record) => $record->paid_at ?: $record->created_at)->first();

        return response()->view('bookings.receipt', compact('booking', 'payment'));
    }
}
