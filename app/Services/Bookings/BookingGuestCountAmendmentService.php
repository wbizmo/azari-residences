<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingModificationRequest;
use App\Models\Property;
use App\Models\User;
use App\Services\Identity\GuestVerificationInvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guest additions on a booked room type that stays within paid unit capacity.
 * No room/rate change, cancellation or removal of verified guests is allowed.
 */
class BookingGuestCountAmendmentService
{
    public function __construct(private readonly BookingModificationService $modifications) {}

    public function approve(
        Booking $booking,
        BookingModificationRequest $request,
        User $staff,
        ?string $note = null
    ): BookingModificationRequest {
        abort_unless($staff->hasPermission('bookings.edit'), 403);

        return DB::transaction(function () use ($booking, $request, $staff, $note): BookingModificationRequest {
            $property = Property::query()->whereKey($booking->property_id)
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            $type = AccommodationType::query()->whereKey($booking->accommodation_type_id)
                ->where('property_id', $property->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            $locked = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();
            $change = BookingModificationRequest::query()
                ->whereKey($request->getKey())->where('booking_id', $locked->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())->firstOrFail();

            if ($change->status !== 'pending' || $change->type !== 'guest_change'
                || ! in_array($locked->status, ['paid', 'confirmed'], true)
                || $locked->checked_in_at || ! $this->modifications->policyAllows($locked, 'guest_change')) {
                throw ValidationException::withMessages(['guest_change' => 'This guest amendment is no longer eligible for approval.']);
            }
            $changes = $change->requested_changes ?? [];
            $adults = (int) ($changes['adult_count'] ?? 0);
            $children = (int) ($changes['child_count'] ?? $locked->children);
            $extraAdults = $adults - (int) $locked->adults;
            $extraChildren = $children - (int) $locked->children;
            if ($adults < 1 || $adults > 12 || $children < 0 || $children > 8
                || $extraAdults < 0 || $extraChildren < 0 || ($extraAdults + $extraChildren) < 1) {
                throw ValidationException::withMessages([
                    'guest_change' => 'This workflow adds named guests only. To remove a verified guest, contact support.',
                ]);
            }

            $perUnit = $type->capacityPerUnit();
            $rooms = max(1, (int) $locked->rooms);
            if (($adults + $children) > $perUnit * $rooms
                || $adults > max(1, (int) ($type->adult_capacity ?: $perUnit)) * $rooms
                || ($type->child_capacity > 0 && $children > (int) $type->child_capacity * $rooms)) {
                throw ValidationException::withMessages(['guest_change' => 'Requested guests exceed this accommodation capacity.']);
            }

            $newAdults = (array) ($changes['new_adults'] ?? []);
            $newChildren = (array) ($changes['new_children'] ?? []);
            if (count($newAdults) !== $extraAdults || count($newChildren) !== $extraChildren) {
                throw ValidationException::withMessages([
                    'guest_change' => 'Provide full names for every added adult and child, including email for each adult.',
                ]);
            }

            $registeredEmails = array_map('strtolower', $locked->guests()
                ->whereNotNull('email')->pluck('email')->all());
            $registeredEmails[] = strtolower((string) $locked->guest_email);
            $adultRows = [];
            foreach ($newAdults as $person) {
                $first = $this->name($person['first_name'] ?? null);
                $last = $this->name($person['last_name'] ?? null);
                $email = strtolower(trim((string) ($person['email'] ?? '')));
                if (!$first || !$last || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                    || strlen($email) > 190 || in_array($email, $registeredEmails, true)) {
                    throw ValidationException::withMessages([
                        'new_adults' => 'Every additional adult needs a unique valid email and full name for identity verification.',
                    ]);
                }
                $registeredEmails[] = $email;
                $adultRows[] = ['first_name' => $first, 'last_name' => $last, 'email' => $email];
            }
            $childRows = [];
            foreach ($newChildren as $person) {
                $first = $this->name($person['first_name'] ?? null);
                $last = $this->name($person['last_name'] ?? null);
                if (!$first || !$last) {
                    throw ValidationException::withMessages(['new_children' => 'Provide full names for all added children.']);
                }
                $childRows[] = ['first_name' => $first, 'last_name' => $last, 'email' => null];
            }

            $nextAdultPosition = (int) $locked->guests()->where('type', 'adult')->max('position');
            $nextChildPosition = (int) $locked->guests()->where('type', 'child')->max('position');
            $invited = [];
            foreach ($adultRows as $person) {
                $invited[] = BookingGuest::query()->create([
                    'booking_id' => $locked->getKey(), 'type' => 'adult',
                    'position' => ++$nextAdultPosition, 'is_lead' => false, ...$person,
                ]);
            }
            foreach ($childRows as $person) {
                BookingGuest::query()->create([
                    'booking_id' => $locked->getKey(), 'type' => 'child',
                    'position' => ++$nextChildPosition, 'is_lead' => false, ...$person,
                ]);
            }
            $oldAdults = (int) $locked->adults;
            $oldChildren = (int) $locked->children;
            $locked->forceFill([
                'adults' => $adults, 'children' => $children, 'modified_at' => now(),
            ])->save();
            $change->forceFill([
                'status' => 'approved', 'reviewed_by' => $staff->getKey(),
                'reviewed_at' => now(), 'accepted_at' => now(),
                'staff_note' => $note ? Str::limit(strip_tags($note), 2000) : null,
            ])->save();
            AuditLog::record('booking.guests_added', $change,
                ['adults' => $oldAdults, 'children' => $oldChildren],
                ['adults' => $adults, 'children' => $children, 'booking_id' => $locked->getKey()],
                actorId: $staff->getKey());

            if ($invited !== []) {
                DB::afterCommit(function () use ($invited): void {
                    foreach ($invited as $guest) {
                        try {
                            app(GuestVerificationInvitationService::class)
                                ->sendInvite($guest, (string) config('app.url'));
                        } catch (\Throwable $e) {
                            report($e); // Invitation is recoverable via existing resend workflow.
                        }
                    }
                });
            }

            return $change->refresh();
        }, 5);
    }

    private function name(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }
        $name = trim(Str::limit(strip_tags($input), 80, ''));
        return $name !== '' ? $name : null;
    }
}
