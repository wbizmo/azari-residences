<?php

namespace App\Services\Travel;

use Illuminate\Support\Facades\DB;

/**
 * Native is intentionally a deferred product decision. PWA telemetry may be
 * absent; absence is NEVER evidence of activation or business return.
 */
final class MobileDemandGate
{
    public function evaluate(int $days = 90): array
    {
        $days = min(max($days,30),180);
        $since=now()->subDays($days);
        $installs=DB::table('analytics_events')->where('event','pwa_installed')
            ->where('occurred_at','>=',$since)->distinct()->count('user_id');
        $returning=DB::table('analytics_events')->where('event','pwa_returned')
            ->where('occurred_at','>=',$since)->distinct()->count('user_id');
        $paid=(int)DB::table('bookings')->where('created_at','>=',$since)
            ->whereIn('status',['paid','confirmed','checked_in','checked_out','completed'])
            ->count();
        // Checkout attribution is not available as a validated PWA-native
        // cohort yet. Return unknown instead of inventing conversion or ROI.
        $attributableBookings = DB::table('analytics_events')
            ->where('event','pwa_booking_confirmed')->where('occurred_at','>=',$since)
            ->distinct()->count('booking_id');
        $observed=$installs>0 && $returning>0 && $attributableBookings>0;
        $thresholds=[
            'installs'=>(int)config('travel.native_min_installs',250),
            'returning'=>(int)config('travel.native_min_returning',75),
            'bookings'=>(int)config('travel.native_min_paid_pwa_bookings',30),
        ];
        $eligible=$observed && $installs >= $thresholds['installs']
            && $returning >= $thresholds['returning']
            && $attributableBookings >= $thresholds['bookings'];
        return ['window_days'=>$days,'observed_cohort'=>$observed,
            'pwa_installs'=>$installs,'returning_pwa_guests'=>$returning,
            'pwa_attributed_paid_bookings'=>$attributableBookings,
            'total_paid_bookings'=>$paid,'thresholds'=>$thresholds,
            'native_code_authorized'=>$eligible && config('travel.native_product_approved',false),
            'recommendation'=>$eligible
                ? 'Product metrics threshold met. Require security, commercial and accessibility review before native release.'
                : 'Do not fork the booking system into a native client. Continue PWA measurement and fix the missing telemetry/cohort first.'];
    }
}
