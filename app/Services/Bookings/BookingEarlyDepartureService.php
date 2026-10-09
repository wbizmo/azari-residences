<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Early departure is a stay-lifecycle fact, NOT automatic refund consent.
 * Original dates/price/contract remain frozen for independent review.
 */
class BookingEarlyDepartureService
{
    public function record(Booking $booking, User $actor, string $reason): Booking
    {
        abort_unless($actor->isStaff() && $actor->hasPermission('bookings.edit'), 403);
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Provide a detailed early-departure reason.']);
        }

        return DB::transaction(function () use ($booking, $actor, $reason): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())
                ->when(DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate())->firstOrFail();

            $tz = (string) ($locked->property_timezone ?: config('localization.platform_timezone', 'UTC'));
            $localToday = CarbonImmutable::now($tz)->startOfDay();
            $originalStart = CarbonImmutable::parse($locked->check_in->toDateString(), $tz);
            $originalEnd = CarbonImmutable::parse($locked->check_out->toDateString(), $tz);

            if (! in_array($locked->status, ['checked_in', 'check_in'], true)
                || ! $locked->checked_in_at
                || ! $localToday->greaterThan($originalStart)
                || ! $localToday->lessThan($originalEnd)) {
                throw ValidationException::withMessages([
                    'booking' => 'Early departure requires an occupied stay with at least one completed night and a future scheduled check-out.',
                ]);
            }

            $unusedNights = (int) $localToday->diffInDays($originalEnd);
            $usedNights = (int) $originalStart->diffInDays($localToday);
            $nightlyBreakdown = data_get($locked->pricing_snapshot, 'nightly_breakdown', []);
            $unusedNightRoomSubtotal = 0.0;
            if (is_array($nightlyBreakdown)) {
                foreach ($nightlyBreakdown as $night) {
                    if (is_array($night)
                        && (string) ($night['date'] ?? '') >= $localToday->toDateString()
                        && (string) ($night['date'] ?? '') < $originalEnd->toDateString()) {
                        $unusedNightRoomSubtotal += max(0, (float) ($night['line_total'] ?? 0));
                    }
                }
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => 'checked_out',
                'checked_out_at' => now(),
                // Preserve the booked checkout, quote and policy snapshots.
                // Future sellability is updated by the canonical status engine.
            ])->save();

            $metadata = [
                'early_departure' => true,
                'original_check_out' => $originalEnd->toDateString(),
                'actual_departure_date' => $localToday->toDateString(),
                'nights_consumed' => $usedNights,
                'nights_unused' => $unusedNights,
                'unused_room_subtotal_before_discount' => round($unusedNightRoomSubtotal, 2),
                'currency' => strtoupper((string) $locked->currency),
                'refund_entitlement' => 'requires_independent_manual_review',
                'provider_refund_settled' => false,
            ];
            BookingStatusHistory::query()->create([
                'booking_id' => $locked->getKey(),
                'changed_by' => $actor->getKey(),
                'from_status' => $from,
                'to_status' => 'checked_out',
                'note' => $reason,
                'metadata' => $metadata,
            ]);
            AuditLog::record('booking.early_departure_recorded', $locked, ['status' => $from], [
                'status' => 'checked_out',
                'actual_departure_date' => $localToday->toDateString(),
                'unused_nights' => $unusedNights,
                'refund_entitlement' => 'manual_review',
            ], actorId: $actor->getKey());

            return $locked->refresh();
        }, 5);
    }
}
