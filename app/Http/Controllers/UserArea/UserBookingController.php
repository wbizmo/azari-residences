<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\TripItinerary;
use App\Services\Bookings\BookingModificationService;
use App\Services\Payments\PaymentScheduleService;
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
            'tripItinerary:id,name',
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

        $tripItineraries = TripItinerary::query()
            ->where('user_id', $request->user()->id)
            ->withCount('bookings')
            ->latest()
            ->limit(30)
            ->get();

        return view('user.bookings.index', compact('bookings', 'status', 'tripItineraries'));
    }

    public function show(
        Request $request,
        string $reference,
        BookingModificationService $modifications,
        PaymentScheduleService $paymentSchedule
    ): View {
        $booking = $request->user()->bookings()
            ->with([
                'property',
                'accommodationType',
                'ratePlan.cancellationPolicy',
                'ratePlan.paymentPolicy',
                'guests',
                'payments.refunds',
                'refunds',
                'modificationRequests',
                'serviceRequests',
                'supportTickets',
                'tripItinerary',
                'review',
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

        $schedule = $paymentSchedule->forBooking($booking);

        $canCancel = in_array($booking->status, ['pending', 'pending_payment', 'approved', 'paid', 'confirmed'], true)
            && ! $booking->checked_in_at && $booking->check_in?->toDateString()
                > now($booking->property_timezone ?: config('localization.platform_timezone', 'UTC'))->toDateString();
        $cancellationQuote = $canCancel
            ? app(\App\Services\Bookings\BookingCancellationQuoteService::class)->quote($booking)
            : null;

        $tripItineraries = TripItinerary::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name']);

        return view('user.bookings.show', compact(
            'booking', 'timeline', 'selfService', 'schedule', 'canCancel', 'cancellationQuote', 'tripItineraries'
        ));
    }

    public function receipt(Request $request, string $reference): Response
    {
        $booking = $request->user()->bookings()
            ->with(['property', 'payments'])
            ->where('reference', $reference)
            ->first();

        abort_unless($booking, 403);

        // A failed, pending or abandoned checkout is not receipt evidence.
        // Mirror the admin and PDF receipt rules, including legacy records.
        abort_unless($booking->receiptAvailable(), 404);
        $payment = $booking->documentPayment();
        abort_unless($payment !== null, 404);

        return response()->view('bookings.receipt', compact('booking', 'payment'));
    }
}
