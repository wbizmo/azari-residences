<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingShareController extends Controller
{
    private const SHAREABLE_STATUSES = [
        'approved', 'confirmed', 'paid', 'check_in', 'checked_in',
        'checked_out', 'completed',
    ];

    public function create(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->owned($request, $reference);
        abort_unless(in_array($booking->status, self::SHAREABLE_STATUSES, true), 422,
            'Only confirmed or completed stays may be shared.');

        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $booking, $token): void {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 404);
            abort_unless(in_array($locked->status, self::SHAREABLE_STATUSES, true), 422);

            BookingShareLink::query()->updateOrCreate(
                ['booking_id' => $locked->id],
                [
                    'created_by' => $request->user()->id,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addHours(24),
                    'revoked_at' => null,
                ]
            );
            AuditLog::record('booking.share_link_issued', $locked, [], [
                'expiry_hours' => 24,
            ]);
        }, 3);

        return back()->with('itinerary_share_url',
            route('public.booking-share.show', ['token' => $token]));
    }

    public function revoke(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->owned($request, $reference);
        DB::transaction(function () use ($booking): void {
            Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $count = BookingShareLink::query()
                ->where('booking_id', $booking->id)->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            if ($count) {
                AuditLog::record('booking.share_link_revoked', $booking);
            }
        }, 3);

        return back()->with('success', 'Shared itinerary link revoked.');
    }

    public function show(string $token): View
    {
        abort_unless((bool) preg_match('/^[a-f0-9]{64}$/D', $token), 404);

        $share = BookingShareLink::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with(['booking'])
            ->firstOrFail();

        abort_unless($share->booking
            && in_array($share->booking->status, self::SHAREABLE_STATUSES, true), 404);

        return view('public.bookings.shared-itinerary', [
            'booking' => $share->booking,
            'expiresAt' => $share->expires_at,
        ]);
    }

    private function owned(Request $request, string $reference): Booking
    {
        return Booking::query()
            ->where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
