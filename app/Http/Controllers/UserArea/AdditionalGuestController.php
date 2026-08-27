<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\BookingGuest;
use App\Models\GuestIdentityDocument;
use App\Models\IdentityAuditHistory;
use App\Services\Identity\IdentityDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdditionalGuestController extends Controller
{
    public function index(Request $request): View
    {
        $guests = BookingGuest::query()
            ->with(['booking.property', 'identityDocument', 'identityLink.userIdentityDocument', 'latestIdentityVerification'])
            ->whereHas('booking', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('type', 'adult')
            ->where('is_lead', false)
            ->latest()
            ->paginate(10);

        return view('user.guests.index', compact('guests'));
    }

    public function store(Request $request, string $reference, BookingGuest $guest, IdentityDocumentService $service): RedirectResponse
    {
        $booking = $request->user()->bookings()->where('reference', $reference)->firstOrFail();
        abort_unless($guest->booking_id === $booking->id && ! $guest->is_lead, 403);
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['passport', 'national_id', 'drivers_licence', 'other_government_id'])],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ]);
        $service->storeGuestIdentity($booking, $guest, $data['document_type'], $request->file('document'), $request->user()->id);
        return back()->with('success', 'Additional guest identity updated securely.');
    }

    public function download(Request $request, string $reference, BookingGuest $guest, GuestIdentityDocument $document): StreamedResponse
    {
        $booking = $request->user()->bookings()->where('reference', $reference)->firstOrFail();
        abort_unless($guest->booking_id === $booking->id && $document->booking_guest_id === $guest->id, 403);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 403);
        IdentityAuditHistory::query()->create([
            'actor_id' => $request->user()->id,
            'document_type' => 'booking_guest',
            'document_id' => $document->id,
            'booking_id' => $booking->id,
            'action' => 'downloaded_by_booking_owner',
        ]);
        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Cache-Control' => 'no-store, private']);
    }
}
