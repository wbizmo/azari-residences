<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** A preview of the frozen booked cancellation policy, not a settled refund. */
class BookingCancellationQuoteService
{
    public function quote(Booking $booking, ?CarbonInterface $asOf = null, bool $noShow = false): array
    {
        $policy = data_get($booking->policy_snapshot, 'cancellation');
        $refundable = data_get($booking->policy_snapshot, 'rate_plan.is_refundable');
        $total = round(max(0, (float) $booking->total), 2);
        // Refund entitlement is calculated from the original verified gross
        // payments, never from a shrinking net balance. Subtract already
        // reserved/settled refunds in the settlement service exactly once.
        $grossPaid = round(max(0, $booking->successfulPaymentsTotal()), 2);
        $paid = round(max(0, $booking->netPaidTotal()), 2);
        $manualReview = ! is_array($policy) && $refundable === null;
        $asOf ??= now();

        $propertyNow = CarbonImmutable::parse($asOf)->setTimezone(
            (string) ($booking->property_timezone ?: config('localization.platform_timezone', 'UTC'))
        );
        $checkIn = $booking->check_in
            ? CarbonImmutable::parse($booking->check_in->toDateString(), $propertyNow->timezone)->startOfDay()
            : null;
        $hoursBeforeArrival = $checkIn ? $propertyNow->diffInRealHours($checkIn, false) : null;
        $freeHours = is_array($policy) ? data_get($policy, 'free_cancel_hours') : null;
        $withinFreeWindow = $freeHours !== null && $hoursBeforeArrival !== null
            && $hoursBeforeArrival >= (float) $freeHours;

        if ($manualReview) {
            $fee = null;
        } elseif ($noShow && data_get($policy, 'no_show_policy') === 'full_charge') {
            $fee = $total;
        } elseif ($refundable === false && ! $withinFreeWindow) {
            $fee = $total;
        } elseif ($withinFreeWindow) {
            $fee = 0.0;
        } else {
            $percentage = min(100, max(0, (float) data_get($policy, 'fee_percentage', 0)));
            $fixed = max(0, (float) data_get($policy, 'fee_amount', 0));
            $firstNight = data_get($policy, 'charge_first_night', false)
                ? min($total, max(0, (float) $booking->nightly_rate * max(1, (int) $booking->rooms))) : 0.0;
            $fee = min($total, round(max($total * $percentage / 100, $fixed, $firstNight), 2));
        }

        return [
            'currency' => strtoupper((string) $booking->currency),
            'policy_known' => ! $manualReview,
            'manual_review_required' => $manualReview,
            'policy_name' => is_array($policy) ? data_get($policy, 'name') : null,
            'free_cancellation' => $withinFreeWindow,
            'cancellation_fee' => $fee,
            'net_paid' => $paid,
            'gross_verified_paid' => $grossPaid,
            // A deposit smaller than the cancellation penalty earns no
            // refund. For example: 20 paid on a 100 stay with a 40 fee.
            'maximum_refund_due' => $fee === null ? null : (float) max(0, round($grossPaid - $fee, 2)),
            'refund_status' => 'not_initiated',
        ];
    }
}
