<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestIdentityDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                        ->orWhere('guest_phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(config('azari.pagination.per_page', 10))
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'search'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['property', 'guests.identityDocument', 'payments']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function receipt(Booking $booking): Response
    {
        $booking->load(['property', 'payments']);
        $payment = $booking->payments->firstWhere('status', 'successful');
        abort_unless($payment, 404);

        return response()->view('bookings.receipt', compact('booking', 'payment'));
    }

    public function document(Booking $booking, GuestIdentityDocument $document): StreamedResponse
    {
        abort_unless($document->guest()->where('booking_id', $booking->id)->exists(), 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function calendar()
    {
        return view('admin.bookings.calendar', [
            'bookings' => Booking::whereNot('status', 'cancelled')->orderBy('check_in')->paginate(10),
        ]);
    }
}
