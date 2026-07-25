<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\GuestIdentityDocument;
use App\Models\IdentityAuditHistory;
use App\Models\IdentityType;
use App\Models\UserIdentityDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdentityManagementController extends Controller
{
    public function index(Request $request): View
    {
        $userDocuments = UserIdentityDocument::query()->with(['user', 'identityType', 'reviewedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('review_status', $request->string('status')))
            ->latest()->paginate(10, ['*'], 'user_documents')->withQueryString();
        $guestDocuments = GuestIdentityDocument::query()->with(['guest.booking.property', 'reviewedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('review_status', $request->string('status')))
            ->latest()->paginate(10, ['*'], 'guest_documents')->withQueryString();
        return view('admin.identities.index', compact('userDocuments', 'guestDocuments'));
    }

    public function types(): View
    {
        return view('admin.identities.types', ['types' => IdentityType::query()->orderBy('sort_order')->paginate(10)]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:80', 'unique:identity_types,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $type = IdentityType::query()->create([...$data, 'is_active' => $request->boolean('is_active'), 'sort_order' => (int) IdentityType::query()->max('sort_order') + 1]);
        AuditLog::record('identity_type.created', $type, [], $type->toArray());
        return back()->with('success', 'Identity type created.');
    }

    public function updateType(Request $request, IdentityType $identityType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('identity_types', 'slug')->ignore($identityType)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = $identityType->toArray();
        $identityType->update([...$data, 'is_active' => $request->boolean('is_active')]);
        AuditLog::record('identity_type.updated', $identityType, $old, $identityType->fresh()->toArray());
        return back()->with('success', 'Identity type updated.');
    }

    public function missing(): View
    {
        $bookings = Booking::query()->with(['property', 'guests.identityDocument', 'guests.identityLink'])
            ->whereNotIn('status', ['cancelled', 'completed', 'checked_out'])
            ->get()->filter(fn (Booking $booking) => $booking->guests->where('type', 'adult')->contains(fn (BookingGuest $guest) => ! $guest->identityDocument && ! $guest->identityLink))
            ->values();
        $page = max(1, (int) request('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $bookings->forPage($page, 10), $bookings->count(), 10, $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        return view('admin.identities.missing', ['bookings' => $paginator]);
    }

    public function audit(): View
    {
        return view('admin.identities.audit', ['history' => IdentityAuditHistory::query()->with(['actor', 'booking'])->latest()->paginate(10)]);
    }

    public function reviewUser(Request $request, UserIdentityDocument $document): RedirectResponse
    {
        $data = $request->validate(['review_status' => ['required', Rule::in(['pending', 'reviewed', 'needs_replacement'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $document->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        IdentityAuditHistory::query()->create(['actor_id' => $request->user()->id, 'document_type' => 'user', 'document_id' => $document->id, 'action' => 'reviewed', 'metadata' => ['status' => $data['review_status']]]);
        return back()->with('success', 'Identity review saved.');
    }

    public function reviewGuest(Request $request, GuestIdentityDocument $document): RedirectResponse
    {
        $data = $request->validate(['review_status' => ['required', Rule::in(['pending', 'reviewed', 'needs_replacement'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $document->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        IdentityAuditHistory::query()->create(['actor_id' => $request->user()->id, 'document_type' => 'booking_guest', 'document_id' => $document->id, 'booking_id' => $document->guest?->booking_id, 'action' => 'reviewed', 'metadata' => ['status' => $data['review_status']]]);
        return back()->with('success', 'Identity review saved.');
    }

    public function downloadUser(UserIdentityDocument $document)
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        IdentityAuditHistory::query()->create(['actor_id' => auth()->id(), 'document_type' => 'user', 'document_id' => $document->id, 'action' => 'downloaded']);
        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Cache-Control' => 'no-store, private']);
    }

    public function downloadGuest(GuestIdentityDocument $document)
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        IdentityAuditHistory::query()->create(['actor_id' => auth()->id(), 'document_type' => 'booking_guest', 'document_id' => $document->id, 'booking_id' => $document->guest?->booking_id, 'action' => 'downloaded']);
        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Cache-Control' => 'no-store, private']);
    }
}
