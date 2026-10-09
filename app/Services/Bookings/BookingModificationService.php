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
    private const SIMPLE_APPROVABLE_TYPES = ['contact_details', 'arrival_time'];

    private const REQUIRED_FIELDS = [
        'date_change' => ['check_in', 'check_out'],
        'guest_change' => ['adult_count'],
        'arrival_time' => ['arrival_time'],
        'room_preference' => ['room_preference'],
        'contact_details' => [],
        'add_extras' => ['add_on_ids'],
        'cancellation' => ['cancellation_reason'],
    ];

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

        $changes = $this->sanitize($changes);
        foreach (self::REQUIRED_FIELDS[$type] as $field) {
            if (! isset($changes[$field]) || $changes[$field] === '' || $changes[$field] === []) {
                throw ValidationException::withMessages([$field => 'This field is required for the selected change request.']);
            }
        }
        if ($type === 'contact_details' && ! filled($changes['email'] ?? null) && ! filled($changes['phone'] ?? null)) {
            throw ValidationException::withMessages(['email' => 'Provide an email address or phone number to update.']);
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

            if (! $this->policyAllows($locked, $type) || (int) $locked->user_id !== (int) $user->getKey()) {
                throw ValidationException::withMessages(['type' => 'Booking is no longer eligible for this change.']);
            }

            $duplicate = BookingModificationRequest::query()
                ->where('booking_id', $locked->getKey())
                ->where('type', $type)
                ->where('status', 'pending')
                ->first();

            if ($duplicate) {
                if ($duplicate->requested_changes !== $changes) {
                    throw ValidationException::withMessages([
                        'type' => 'An earlier change request is still pending. Resolve it before submitting different changes.',
                    ]);
                }

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
                'requested_changes' => $changes,
                'guest_note' => $note ? Str::limit(strip_tags($note), 2000) : null,
            ]);

            AuditLog::record('booking.modification_requested', $request, [], [
                'type' => $type,
                'booking_reference' => $locked->reference,
            ], actorId: $user->getKey());

            return $request;
        }, 5);
    }

    /**
     * Reviews non-financial amendments safely. Date/room/guest/extras and
     * cancellation requests require the separate inventory/financial
     * orchestration and cannot be 'approved' by changing a status alone.
     */
    public function review(
        Booking $booking,
        BookingModificationRequest $change,
        User $reviewer,
        string $decision,
        ?string $note = null
    ): BookingModificationRequest {
        if (! in_array($decision, ['approve', 'decline'], true)) {
            throw ValidationException::withMessages(['decision' => 'Invalid review decision.']);
        }

        abort_unless($reviewer->hasPermission('bookings.edit'), 403);

        return DB::transaction(function () use ($booking, $change, $reviewer, $decision, $note): BookingModificationRequest {
            $lockedBooking = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();

            $lockedChange = BookingModificationRequest::query()
                ->whereKey($change->getKey())->where('booking_id', $lockedBooking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();

            if ($lockedChange->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
            }

            $changes = $lockedChange->requested_changes ?? [];
            if ($decision === 'approve') {
                if (! in_array($lockedChange->type, self::SIMPLE_APPROVABLE_TYPES, true)) {
                    throw ValidationException::withMessages([
                        'decision' => 'Changes affecting inventory, policies or money require the dedicated reprice-and-confirm workflow.',
                    ]);
                }

                if (! $this->policyAllows($lockedBooking, $lockedChange->type)) {
                    throw ValidationException::withMessages(['decision' => 'Booking is no longer eligible for this change.']);
                }

                if ($lockedChange->type === 'contact_details') {
                    $updates = [];
                    if (filled($changes['email'] ?? null)) {
                        $updates['guest_email'] = $changes['email'];
                    }
                    if (filled($changes['phone'] ?? null)) {
                        $updates['guest_phone'] = $changes['phone'];
                    }
                    if ($updates === []) {
                        throw ValidationException::withMessages(['decision' => 'No contact details were supplied.']);
                    }
                    $lockedBooking->forceFill($updates)->save();
                } elseif ($lockedChange->type === 'arrival_time') {
                    if (! filled($changes['arrival_time'] ?? null)) {
                        throw ValidationException::withMessages(['decision' => 'No arrival time was supplied.']);
                    }
                    $lockedBooking->forceFill(['arrival_time' => $changes['arrival_time']])->save();
                }
            }

            $lockedChange->forceFill([
                'status' => $decision === 'approve' ? 'approved' : 'declined',
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'staff_note' => $note ? Str::limit(strip_tags($note), 2000) : null,
            ])->save();

            $lockedBooking->forceFill(['modified_at' => now()])->save();

            AuditLog::record('booking.modification_reviewed', $lockedChange, [], [
                'decision' => $decision,
                'type' => $lockedChange->type,
                'booking_id' => $lockedBooking->getKey(),
            ], actorId: $reviewer->getKey());

            return $lockedChange->refresh();
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

        $hours = now(config('localization.platform_timezone', 'UTC'))->diffInHours($booking->check_in->startOfDay(), false);

        return $hours >= 24;
    }

    private function sanitize(array $changes): array
    {
        $allowed = [
            'check_in', 'check_out', 'arrival_time', 'guest_count', 'adult_count',
            'child_count', 'new_adults', 'new_children', 'room_preference', 'email', 'phone', 'add_on_ids',
            'cancellation_reason',
        ];

        return collect($changes)
            ->only($allowed)
            ->map(fn ($value) => is_string($value) ? Str::limit(strip_tags($value), 500) : $value)
            ->all();
    }
}
