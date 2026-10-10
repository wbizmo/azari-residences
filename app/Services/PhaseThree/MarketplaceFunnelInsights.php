<?php

namespace App\Services\PhaseThree;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Canonical, timezone-scoped funnel counts. No customer identities or mixed-currency revenue in reports. */
final class MarketplaceFunnelInsights
{
    public const VERSION = 1;

    public function assignExperiment(string $experiment, string $subject, int $variants = 2): int
    {
        if (! preg_match('/^[a-z0-9._-]{1,64}$/', $experiment) || $subject === ''
            || $variants < 2 || $variants > 8) {
            throw new \InvalidArgumentException('Invalid experiment assignment.');
        }
        $bytes = hash_hmac('sha256', $experiment.'|'.$subject, (string) config('app.key'));
        return hexdec(substr($bytes, 0, 8)) % $variants;
    }

    public function funnel(CarbonImmutable $from, CarbonImmutable $through): array
    {
        if ($from->gt($through) || $from->diffInDays($through) > 366) {
            throw new \InvalidArgumentException('Invalid analytics period.');
        }
        $steps = ['search_submitted','results_viewed','property_viewed',
            'rate_selected','checkout_started','payment_started','booking_confirmed'];
        $events = AnalyticsEvent::query()->whereBetween('occurred_at', [$from, $through])
            ->whereIn('event', $steps)->selectRaw('event, COUNT(*) AS aggregate')
            ->groupBy('event')->pluck('aggregate', 'event');
        // Conversion is assessed on backend-verified booking/payment facts.
        $settledBookings = Booking::query()->whereBetween('created_at', [$from, $through])
            ->whereHas('payments', fn ($q) => $q->where('status', 'successful'))->count();
        return ['schema_version' => self::VERSION,
            'period' => ['from' => $from->toIso8601String(), 'to' => $through->toIso8601String()],
            'events' => collect($steps)->mapWithKeys(fn ($step) => [$step => (int) ($events[$step] ?? 0)])->all(),
            'verified_paid_bookings' => $settledBookings];
    }

    public function ownerBookings(User $owner, CarbonImmutable $from, CarbonImmutable $through): array
    {
        $properties = Property::query()->where('owner_id', $owner->getKey())->pluck('id');
        if ($properties->isEmpty()) return [];
        // Group by currency so USD, NGN, etc. are never summed together.
        return Booking::query()->whereIn('property_id', $properties)
            ->whereBetween('created_at', [$from, $through])
            ->selectRaw('currency, COUNT(*) as bookings, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled', ['cancelled'])
            ->groupBy('currency')->get()->map(fn ($row) => [
                'currency' => $row->currency, 'bookings' => (int) $row->bookings,
                'cancelled' => (int) $row->cancelled,
            ])->all();
    }
}
