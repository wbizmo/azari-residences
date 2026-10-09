<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingModificationRequest;
use App\Services\Bookings\BookingModificationService;
use App\Models\AuditLog;
use App\Models\BookingOperationalNote;
use App\Services\Bookings\AzariBookingLifecycle;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BookingManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $bookings = Booking::query()
            ->with('property')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhere('guest_phone', 'like', "%{$search}%")
                        ->orWhereHas('property', fn ($property) => $property->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(config('azari.pagination.per_page', 10))
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'search'));
    }

    public function show(Booking $booking, AzariBookingLifecycle $lifecycle)
    {
        $booking->load([
            'property',
            'accommodationType',
            'ratePlan',
            'refunds',
            'modificationRequests.user',
            'operationalNotes.author',
        ]);

        $guests = $booking->guests()->orderBy('position')->paginate(10, ['*'], 'guests_page')->withQueryString();
        $payments = $booking->payments()->latest()->paginate(10, ['*'], 'payments_page')->withQueryString();

        $statusHistory = $booking->statusHistory()->with('changedBy')->paginate(10, ['*'], 'status_page')->withQueryString();
        $allowedTransitions = $lifecycle->allowedTransitions($booking);

        return view('admin.bookings.show', compact(
            'booking',
            'guests',
            'payments',
            'statusHistory',
            'allowedTransitions'
        ));
    }

    public function storeNote(Request $request, Booking $booking): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', 'in:staff,guest'],
        ]);

        $note = BookingOperationalNote::query()->create([
            'booking_id' => $booking->getKey(),
            'author_id' => $request->user()?->getKey(),
            'visibility' => $data['visibility'],
            'body' => $data['body'],
        ]);

        AuditLog::record('booking.operational_note_added', $note, [], [
            'booking_reference' => $booking->reference,
            'visibility' => $data['visibility'],
        ]);

        return back()->with('success', 'Operational note added.');
    }

    public function transition(
        Request $request,
        Booking $booking,
        AzariBookingLifecycle $lifecycle
    ): \Illuminate\Http\RedirectResponse {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['status'] === 'cancelled') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Use the audited Cancel booking section for this action.',
            ]);
        }

        $lifecycle->transition(
            $booking,
            $data['status'],
            $request->user()?->getKey(),
            $data['note'] ?? null
        );

        return back()->with('success', 'Booking status updated.');
    }

    public function reviewModification(
        Request $request,
        Booking $booking,
        BookingModificationRequest $modification,
        BookingModificationService $service
    ): \Illuminate\Http\RedirectResponse {
        abort_unless((int) $modification->booking_id === (int) $booking->getKey(), 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'staff_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->review($booking, $modification, $request->user(),
            $data['decision'], $data['staff_note'] ?? null);

        return back()->with('success', 'Booking change request reviewed.');
    }

    public function receipt(Booking $booking): Response
    {
        $booking->load(['property', 'payments']);
        $payment = $booking->payments->sortByDesc(fn ($record) => $record->paid_at ?: $record->created_at)->first();

        return response()->view('bookings.receipt', compact('booking', 'payment'));
    }

    public function calendar()
    {
        return view('admin.bookings.calendar', [
            'bookings' => Booking::whereNot('status', 'cancelled')->orderBy('check_in')->paginate(10)->withQueryString(),
        ]);
    }
}
