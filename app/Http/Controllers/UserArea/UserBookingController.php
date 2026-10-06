<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Services\Bookings\BookingModificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserBookingController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'upcoming');
        $today = today();

        $query = $request->user()->bookings()->with([
            'property',
            'accommodationType',
            'ratePlan',
            'payments',
            'modificationRequests' => fn ($q) => $q->where('status', 'pending'),
        ]);

        match ($status) {
            'current' => $query
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->whereDate('check_in', '<=', $today)
                ->whereDate('check_out', '>=', $today),
            'upcoming' => $query
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->whereDate('check_in', '>', $today),
            'past' => $query
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($today): void {
                    $q->whereIn('status', ['completed', 'checked_out', 'no_show'])
                        ->orWhereDate('check_out', '<', $today);
                }),
            'cancelled' => $query->where('status', 'cancelled'),
            'pending-payment' => $query
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->where(function ($q): void {
                    $q->whereIn('status', ['pending', 'pending_payment', 'confirmed'])
                        ->orWhereHas('payments', fn ($payments) => $payments->whereNot('status', 'successful'));
                }),
            'all' => null,
            default => $status = 'upcoming',
        };

        $bookings = $query
            ->orderByRaw("CASE WHEN check_in >= ? THEN 0 ELSE 1 END", [$today->toDateString()])
            ->orderBy('check_in')
            ->paginate(10)
            ->withQueryString();

        return view('user.bookings.index', compact('bookings', 'status'));
    }

    public function show(
        Request $request,
        string $reference,
        BookingModificationService $modifications
    ): View {
        $booking = $request->user()->bookings()
            ->with([
                'property',
                'accommodationType',
                'ratePlan.cancellationPolicy',
                'ratePlan.paymentPolicy',
                'guests.identityDocument',
                'guests.identityLink.userIdentityDocument.identityType',
                'guests.latestIdentityVerification',
                'identityLinks',
                'payments.refunds',
                'refunds',
                'modificationRequests',
                'serviceRequests',
                'supportTickets',
            ])
            ->withCount('payments')
            ->where('reference', $reference)
            ->first();

        abort_unless($booking, 403);

        $timeline = $booking->statusHistory()
            ->paginate(10, ['*'], 'timeline')
            ->withQueryString();

        $selfService = collect([
            'date_change',
            'guest_change',
            'arrival_time',
            'room_preference',
            'contact_details',
            'add_extras',
            'cancellation',
        ])->mapWithKeys(fn ($type) => [
            $type => $modifications->policyAllows($booking, $type),
        ])->all();

        return view('user.bookings.show', compact('booking', 'timeline', 'selfService'));
    }

    public function receipt(Request $request, string $reference): Response
    {
        $booking = $request->user()->bookings()
            ->with(['property', 'payments'])
            ->where('reference', $reference)
            ->first();

        abort_unless($booking, 403);

        $payment = $booking->payments
            ->sortByDesc(fn ($record) => $record->paid_at ?: $record->created_at)
            ->first();

        return response()->view('bookings.receipt', compact('booking', 'payment'));
    }
}
