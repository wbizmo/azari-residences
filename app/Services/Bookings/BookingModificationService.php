<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingModificationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingModificationService
{
    private const TYPES = [
        'date_change', 'guest_change', 'arrival_time', 'room_preference',
        'contact_details', 'add_extras', 'cancellation',
    ];

    public function request(Booking $booking, User $user, string $type, array $changes, ?string $note = null): BookingModificationRequest
    {
        if ((int) $booking->user_id !== (int) $user->getKey()) {
            abort(403);
        }

        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported booking change request.']);
        }

        if (! $this->policyAllows($booking, $type)) {
            throw ValidationException::withMessages(['type' => 'This booking is no longer eligible for that self-service change.']);
        }

        return DB::transaction(function () use ($booking, $user, $type, $changes, $note): BookingModificationRequest {
            $locked = Booking::query()
                ->whereKey($booking->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            $duplicate = BookingModificationRequest::query()
                ->where('booking_id', $locked->getKey())
                ->where('type', $type)
                ->where('status', 'pending')
                ->first();

            if ($duplicate) {
                return $duplicate;
            }

            do {
                $reference = 'MOD-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
            } while (BookingModificationRequest::query()->where('reference', $reference)->exists());

            $request = BookingModificationRequest::query()->create([
                'reference' => $reference,
                'booking_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'type' => $type,
                'status' => 'pending',
                'requested_changes' => $this->sanitize($changes),
                'guest_note' => $note ? Str::limit(strip_tags($note), 2000) : null,
            ]);

            AuditLog::record('booking.modification_requested', $request, [], [
                'type' => $type,
                'booking_reference' => $locked->reference,
            ], actorId: $user->getKey());

            return $request;
        }, 5);
    }

    public function policyAllows(Booking $booking, string $type): bool
    {
        if ($booking->isCancelled() || in_array($booking->status, ['completed', 'checked_out', 'no_show'], true)) {
            return false;
        }

        if (in_array($type, ['arrival_time', 'room_preference', 'contact_details'], true)) {
            return true;
        }

        if (! $booking->check_in) {
            return false;
        }

        $hours = now(config('azari.timezone', 'Africa/Lagos'))->diffInHours($booking->check_in->startOfDay(), false);

        return $hours >= 24;
    }

    private function sanitize(array $changes): array
    {
        $allowed = [
            'check_in', 'check_out', 'arrival_time', 'guest_count', 'adult_count',
            'child_count', 'room_preference', 'email', 'phone', 'add_on_ids',
            'cancellation_reason',
        ];

        return collect($changes)
            ->only($allowed)
            ->map(fn ($value) => is_string($value) ? Str::limit(strip_tags($value), 500) : $value)
            ->all();
    }
}
