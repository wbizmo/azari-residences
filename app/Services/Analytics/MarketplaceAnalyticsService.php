<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MarketplaceAnalyticsService
{
    public function summary(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= now()->toImmutable()->subDays(29)->startOfDay();
        $to ??= now()->toImmutable()->endOfDay();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $bookingBase = Booking::query()
            ->whereBetween('created_at', [$from, $to]);

        $bookings = (clone $bookingBase)->count();
        $gbv = (float) (clone $bookingBase)
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->sum('total');

        $payments = Payment::query()
            ->where('status', Payment::SUCCESSFUL)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $refunds = Refund::query()
            ->where('status', 'successful')
            ->whereBetween('processed_at', [$from, $to])
            ->sum('amount');

        $netRevenue = max(0, round((float) $payments - (float) $refunds, 2));

        $stayRows = Booking::query()
            ->whereIn('status', ['confirmed', 'paid', 'check_in', 'checked_in', 'checked_out', 'completed'])
            ->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>=', $from->toDateString())
            ->get(['check_in', 'check_out', 'rooms', 'total', 'created_at']);

        $soldUnitNights = $stayRows->sum(function (Booking $booking) use ($from, $to): int {
            $start = CarbonImmutable::parse($booking->check_in)->max($from->startOfDay());
            $end = CarbonImmutable::parse($booking->check_out)->min($to->addDay()->startOfDay());
            $nights = max(0, $start->diffInDays($end));

            return $nights * max(1, (int) $booking->rooms);
        });

        $periodDays = max(1, $from->startOfDay()->diffInDays($to->startOfDay()) + 1);
        $defaultAvailableUnitNights = (int) DB::table('accommodation_types')
            ->where('is_active', true)
            ->sum('total_inventory') * $periodDays;

        $overrideDelta = (int) DB::table('inventory_dates')
            ->join('accommodation_types', 'accommodation_types.id', '=', 'inventory_dates.accommodation_type_id')
            ->where('accommodation_types.is_active', true)
            ->whereBetween('inventory_dates.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(
                CASE
                    WHEN inventory_dates.stop_sell = 1 THEN -accommodation_types.total_inventory
                    ELSE (
                        CASE
                            WHEN (
                                COALESCE(inventory_dates.sellable_inventory, accommodation_types.total_inventory)
                                - COALESCE(inventory_dates.maintenance_inventory, 0)
                            ) < 0 THEN 0
                            ELSE (
                                COALESCE(inventory_dates.sellable_inventory, accommodation_types.total_inventory)
                                - COALESCE(inventory_dates.maintenance_inventory, 0)
                            )
                        END
                        - accommodation_types.total_inventory
                    )
                END
            ) AS delta')
            ->value('delta');

        $availableUnitNights = max(0, $defaultAvailableUnitNights + $overrideDelta);

        $cancelled = (clone $bookingBase)->where('status', 'cancelled')->count();
        $noShows = (clone $bookingBase)->where('status', 'no_show')->count();

        $paymentSuccess = Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', Payment::SUCCESSFUL)
            ->count();
        $paymentFailed = Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', Payment::FAILED)
            ->count();

        $voucherBookings = (clone $bookingBase)->whereNotNull('voucher_id')->count();
        $discountTotal = (float) (clone $bookingBase)->sum('discount_total');

        return [
            'period' => ['from' => $from, 'to' => $to],
            'bookings' => $bookings,
            'gbv' => round($gbv, 2),
            'net_revenue' => $netRevenue,
            'sold_unit_nights' => (int) $soldUnitNights,
            'available_unit_nights' => (int) $availableUnitNights,
            'occupancy' => $availableUnitNights > 0 ? round(($soldUnitNights / $availableUnitNights) * 100, 2) : 0.0,
            'adr' => $soldUnitNights > 0 ? round($netRevenue / $soldUnitNights, 2) : 0.0,
            'revpar' => $availableUnitNights > 0 ? round($netRevenue / $availableUnitNights, 2) : 0.0,
            'cancellation_rate' => $bookings > 0 ? round(($cancelled / $bookings) * 100, 2) : 0.0,
            'no_show_rate' => $bookings > 0 ? round(($noShows / $bookings) * 100, 2) : 0.0,
            'average_booking_value' => $bookings > 0 ? round($gbv / $bookings, 2) : 0.0,
            'average_length_of_stay' => $stayRows->count() > 0 ? round((float) $stayRows->avg(fn (Booking $booking) => max(1, (int) $booking->nights)), 2) : 0.0,
            'payment_success_rate' => ($paymentSuccess + $paymentFailed) > 0
                ? round(($paymentSuccess / ($paymentSuccess + $paymentFailed)) * 100, 2)
                : 0.0,
            'voucher_bookings' => $voucherBookings,
            'discount_total' => round($discountTotal, 2),
            'funnel' => $this->funnel($from, $to),
        ];
    }

    public function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = AnalyticsEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('event, COUNT(*) as aggregate')
            ->groupBy('event')
            ->pluck('aggregate', 'event');

        return collect([
            'homepage_viewed',
            'search_started',
            'search_submitted',
            'results_viewed',
            'property_viewed',
            'rate_selected',
            'checkout_started',
            'payment_started',
            'payment_failed',
            'booking_confirmed',
            'booking_cancelled',
        ])->mapWithKeys(fn (string $event) => [$event => (int) ($events[$event] ?? 0)])->all();
    }
}
