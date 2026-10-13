<?php

namespace App\Services\Bookings;

use App\Models\AccommodationBookingApproval;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval is a separate, audited request state. The booking itself retains
 * canonical statuses used by Pesapal, finance, inventory and documents.
 *
 * Lock order matches checkout: property -> accommodation -> calendar -> booking.
 * A submitted request occupies inventory only until its finite expiry.
 */
final class AccommodationBookingApprovalService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
    ) {}

    public function underReview(Booking $booking, User $actor): Booking
    {
        return $this->decide($booking, $actor, 'under_review', null);
    }

    public function approve(Booking $booking, User $actor, ?string $note = null): Booking
    {
        return $this->decide($booking, $actor, 'approved', $note);
    }

    public function decline(Booking $booking, User $actor, string $reason): Booking
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Provide a reason of at least 10 characters.']);
        }

        return $this->decide($booking, $actor, 'declined', trim($reason));
    }

    /**
     * An alternative is a suggestion, NOT an automatic amendment, inventory
     * hold, or payment quote. The guest must request a fresh priced stay.
     */
    public function propose(
        Booking $booking,
        User $actor,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        string $note
    ): Booking {
        if ($checkIn->isPast() || ! $checkIn->lessThan($checkOut) || mb_strlen(trim($note)) < 10) {
            throw ValidationException::withMessages(['proposal' => 'Choose valid future dates and explain the alternative.']);
        }

        return $this->decide($booking, $actor, 'under_review', trim($note), [
            'proposed_check_in' => $checkIn->toDateString(),
            'proposed_check_out' => $checkOut->toDateString(),
        ]);
    }

    private function decide(
        Booking $booking,
        User $actor,
        string $decision,
        ?string $note,
        array $proposal = []
    ): Booking {
        return DB::transaction(function () use ($booking, $actor, $decision, $note, $proposal): Booking {
            $property = Property::query()->whereKey($booking->property_id)
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())
                ->firstOrFail();

            // Owners cannot review a different owner's accommodation, even
            // when they guess a booking ID or possess a historical reference.
            abort_unless($actor->isAdministrator()
                || ($actor->isStaff() && $actor->hasPermission('bookings.edit'))
                || (! $actor->isStaff() && (int) $property->owner_id === (int) $actor->getKey()
                    && $property->owner_id !== null), 404);

            $locked = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())
                ->firstOrFail();
            $review = AccommodationBookingApproval::query()
                ->where('booking_id', $locked->id)
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $q) => $q->lockForUpdate())
                ->firstOrFail();

            if (($decision === 'under_review' && $review->status === 'under_review' && $proposal === [])
                || ($decision === 'approved' && $review->status === 'approved')) {
                return $locked->load('approval'); // Idempotent retry.
            }

            if (! in_array($review->status, ['submitted', 'under_review'], true)
                || $locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'booking' => 'This request has already been decided or can no longer be reviewed.',
                ]);
            }

            if (! $locked->expires_at || $locked->expires_at->isPast()
                || $locked->check_in?->isPast()) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking request expired. Ask the guest to submit new dates.',
                ]);
            }

            if ($decision === 'approved') {
                // Refuse approval of unpublished or repriced inventory. The
                // request never silently changes dates, room, rate or amount.
                if (! $property->is_published
                    || in_array($property->status, ['inactive','unavailable','maintenance','archived'], true)) {
                    throw ValidationException::withMessages(['booking' => 'Property is no longer bookable.']);
                }

                $type = $locked->accommodationType;
                $plan = $locked->ratePlan;
                if (! $type || ! $type->is_active || ! $type->is_published
                    || ($plan && (! $plan->is_active || ! $plan->is_public))) {
                    throw ValidationException::withMessages(['booking' => 'Room or rate is no longer public.']);
                }

                $this->availability->lockInventoryRange($type, $locked->check_in, $locked->check_out);
                $this->availability->assertRules(
                    $property, $locked->check_in, $locked->check_out,
                    (int) $locked->adults, (int) $locked->children,
                    (int) $locked->rooms, $type, $plan
                );

                if (! $this->availability->availableForProperty(
                    $property, $locked->check_in, $locked->check_out,
                    (int) $locked->rooms, $type->getKey(), $locked->getKey()
                )) {
                    throw ValidationException::withMessages(['booking' => 'Inventory changed. Please request fresh dates.']);
                }

                $quote = $this->pricing->quote(
                    $property, $locked->check_in, $locked->check_out, [], $type, $plan,
                    (int) $locked->rooms
                );
                $frozen = (array) $locked->pricing_snapshot;
                if (strtoupper((string) ($frozen['currency'] ?? '')) !== strtoupper((string) $quote['currency'])
                    || abs(round((float) ($frozen['total'] ?? -1), 2) - round((float) $quote['total'], 2)) >= 0.005
                    || (int) ($frozen['quantity'] ?? 1) !== (int) $locked->rooms
                    || (int) ($frozen['rate_plan_id'] ?? 0) !== (int) ($plan?->getKey() ?? 0)) {
                    throw ValidationException::withMessages(['booking' => 'The original quote is stale. The guest must review and submit a new request.']);
                }

                $locked->forceFill([
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes(max(15, (int) config('azari.booking.unpaid_booking_minutes', 60))),
                ])->save();
                $review->forceFill([
                    'status' => 'approved',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ])->save();

                BookingStatusHistory::query()->create([
                    'booking_id' => $locked->id,
                    'changed_by' => $actor->id,
                    'from_status' => 'pending',
                    'to_status' => 'pending_payment',
                    'note' => 'Accommodation request approved. Payment may now begin.',
                    'metadata' => ['approval' => 'approved'],
                ]);

                return $locked->refresh()->load('approval');
            }

            if ($decision === 'declined') {
                if ($locked->payments()->where('status', 'successful')->exists()) {
                    throw ValidationException::withMessages(['booking' => 'Financially settled bookings require the regular cancellation and refund workflow.']);
                }
                // Audited cancellation releases its inventory without falsely
                // recording a refund as settled.
                app(BookingCancellationService::class)->cancel(
                    $locked, $actor->id, $note ?: 'Accommodation request declined.'
                );
                $review->forceFill([
                    'status' => 'declined',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ])->save();

                return $locked->refresh()->load('approval');
            }

            $review->forceFill([
                'status' => 'under_review',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                ...$proposal,
            ])->save();

            BookingStatusHistory::query()->create([
                'booking_id' => $locked->id,
                'changed_by' => $actor->id,
                'from_status' => 'pending',
                'to_status' => 'pending',
                'note' => $proposal ? 'Alternative dates proposed. Guest action required.' : 'Request under review.',
                'metadata' => ['approval' => 'under_review', ...$proposal],
            ]);

            return $locked->refresh()->load('approval');
        }, 5);
    }
}
