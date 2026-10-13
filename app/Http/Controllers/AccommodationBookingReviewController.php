<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Bookings\AccommodationBookingApprovalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccommodationBookingReviewController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $isStaff = $actor->isStaff();

        $bookings = Booking::query()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereHas('approval', fn ($q) => $q->whereIn('status', ['submitted', 'under_review']))
            ->when(! $isStaff, fn ($q) => $q->whereHas('property',
                fn ($properties) => $properties->where('owner_id', $actor->getKey())))
            ->with(['approval', 'property:id,name,owner_id', 'accommodationType:id,name'])
            ->latest('created_at')
            ->paginate(20)->withQueryString();

        return view('bookings.manual-approval-inbox', [
            'bookings' => $bookings,
            'isStaff' => $isStaff,
        ]);
    }

    public function underReview(
        Request $request,
        Booking $booking,
        AccommodationBookingApprovalService $service
    ): RedirectResponse {
        $service->underReview($booking, $request->user());
        return back()->with('success', 'Booking request marked as under review.');
    }

    public function approve(
        Request $request,
        Booking $booking,
        AccommodationBookingApprovalService $service
    ): RedirectResponse {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $service->approve($booking, $request->user(), $data['note'] ?? null);
        return back()->with('success', 'Request approved. The guest can now proceed under the frozen payment policy.');
    }

    public function decline(
        Request $request,
        Booking $booking,
        AccommodationBookingApprovalService $service
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $service->decline($booking, $request->user(), $data['reason']);
        return back()->with('success', 'Booking request declined. No payment was collected.');
    }

    public function propose(
        Request $request,
        Booking $booking,
        AccommodationBookingApprovalService $service
    ): RedirectResponse {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $service->propose(
            $booking, $request->user(),
            CarbonImmutable::parse($data['check_in']),
            CarbonImmutable::parse($data['check_out']),
            $data['note']
        );

        return back()->with('success', 'Alternative dates proposed. The guest must submit a new request and accept a fresh quote.');
    }
}
