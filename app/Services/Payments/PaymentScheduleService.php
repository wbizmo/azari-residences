<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Carbon\CarbonImmutable;

class PaymentScheduleService
{
    public function forBooking(Booking $booking): array
    {
        $total = round((float) $booking->total, 2);
        $paid = min($total, round((float) $booking->successfulPaymentsTotal(), 2));
        $balance = max(0, round($total - $paid, 2));

        $policy = (array) data_get($booking->policy_snapshot, 'payment', []);
        $type = (string) ($policy['payment_type'] ?? 'full_prepayment');
        $depositType = (string) ($policy['deposit_type'] ?? '');
        $depositValue = (float) ($policy['deposit_value'] ?? 0);
        $balanceDueDays = isset($policy['balance_due_days_before_arrival'])
            ? (int) $policy['balance_due_days_before_arrival']
            : null;

        $depositTarget = 0.0;

        if ($type === 'deposit') {
            $depositTarget = $depositType === 'percentage'
                ? round($total * ($depositValue / 100), 2)
                : round($depositValue, 2);

            $depositTarget = max(0, min($total, $depositTarget));
        }

        $kind = 'full';
        $requiredNow = $balance;
        $payableNow = $balance;
        $canDefer = false;
        $dueOn = null;
        $confirmationThreshold = $total;

        if ($type === 'deposit') {
            $confirmationThreshold = $depositTarget;
            $depositRemaining = max(0, round($depositTarget - $paid, 2));

            if ($depositRemaining > 0) {
                $kind = 'deposit';
                $requiredNow = min($balance, $depositRemaining);
                $payableNow = $requiredNow;
            } else {
                $kind = 'balance';
                $payableNow = $balance;

                if ($balanceDueDays !== null && $booking->check_in) {
                    $dueOn = CarbonImmutable::parse($booking->check_in)
                        ->subDays(max(0, $balanceDueDays))
                        ->startOfDay();

                    $canDefer = now(config('azari.timezone', 'Africa/Lagos'))
                        ->startOfDay()
                        ->lessThan($dueOn);
                    $requiredNow = $canDefer ? 0.0 : $balance;
                } else {
                    $canDefer = true;
                    $requiredNow = 0.0;
                }
            }
        } elseif (in_array($type, ['pay_later', 'pay_at_property'], true)) {
            $kind = 'balance';
            $requiredNow = 0.0;
            $payableNow = $balance;
            $canDefer = true;
            $confirmationThreshold = 0.0;

            if ($balanceDueDays !== null && $booking->check_in) {
                $dueOn = CarbonImmutable::parse($booking->check_in)
                    ->subDays(max(0, $balanceDueDays))
                    ->startOfDay();

                if (now(config('azari.timezone', 'Africa/Lagos'))->startOfDay()->greaterThanOrEqualTo($dueOn)) {
                    $requiredNow = $balance;
                    $canDefer = false;
                }
            }
        }

        return [
            'payment_type' => $type,
            'payment_name' => $policy['name'] ?? null,
            'total' => $total,
            'paid' => $paid,
            'balance' => $balance,
            'deposit_target' => $depositTarget,
            'required_now' => round($requiredNow, 2),
            'payable_now' => round($payableNow, 2),
            'payment_kind' => $kind,
            'can_defer' => $canDefer,
            'due_on' => $dueOn?->toDateString(),
            'confirmation_threshold' => round($confirmationThreshold, 2),
            'confirmation_threshold_met' => $paid + 0.009 >= $confirmationThreshold,
            'fully_paid' => $balance <= 0.009,
        ];
    }

    public function amountToCollect(Booking $booking): array
    {
        $schedule = $this->forBooking($booking);

        $amount = $schedule['required_now'] > 0
            ? $schedule['required_now']
            : $schedule['payable_now'];

        return [
            ...$schedule,
            'amount' => round((float) $amount, 2),
        ];
    }
}
