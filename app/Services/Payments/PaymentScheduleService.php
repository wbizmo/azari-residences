<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

                    $canDefer = now(config('localization.platform_timezone', 'UTC'))
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

                if (now(config('localization.platform_timezone', 'UTC'))->startOfDay()->greaterThanOrEqualTo($dueOn)) {
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


    public function confirmDeferredBooking(Booking $booking, ?int $actorId = null): Booking
    {
        return DB::transaction(function () use ($booking, $actorId): Booking {
            $locked = Booking::query()
                ->whereKey($booking->getKey())
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn (Builder $query) => $query->lockForUpdate()
                )
                ->firstOrFail();

            if (in_array($locked->status, ['cancelled', 'completed', 'checked_out', 'no_show'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking can no longer be confirmed.',
                ]);
            }

            if ($locked->status === 'confirmed') {
                return $locked;
            }

            $schedule = $this->forBooking($locked);

            if (
                ! in_array($schedule['payment_type'], ['pay_later', 'pay_at_property'], true)
                || ! $schedule['can_defer']
                || $schedule['required_now'] > 0
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'This booking requires payment before confirmation.',
                ]);
            }

            $from = $locked->status;
            $locked->update([
                'status' => 'confirmed',
                'expires_at' => null,
            ]);

            BookingStatusHistory::query()->create([
                'booking_id' => $locked->getKey(),
                'changed_by' => $actorId,
                'from_status' => $from,
                'to_status' => 'confirmed',
                'note' => 'Booking confirmed under deferred payment terms.',
                'metadata' => [
                    'payment_type' => $schedule['payment_type'],
                    'balance' => $schedule['balance'],
                    'due_on' => $schedule['due_on'],
                ],
            ]);

            app(\App\Services\Analytics\AnalyticsTracker::class)->track(
                'booking_confirmed',
                [
                    'user_id' => $locked->user_id,
                    'booking_id' => $locked->getKey(),
                    'property_id' => $locked->property_id,
                    'accommodation_type_id' => $locked->accommodation_type_id,
                    'rate_plan_id' => $locked->rate_plan_id,
                    'source' => 'deferred_payment',
                    'payload' => ['payment_type' => $schedule['payment_type']],
                ],
                hash('sha256', 'booking-confirmed|'.$locked->getKey())
            );

            return $locked->refresh();
        }, 5);
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
