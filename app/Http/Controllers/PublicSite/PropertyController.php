<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\RecentlyViewedProperty;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use App\Services\Reviews\ReviewSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PropertyController extends Controller
{
    public function show(
        Request $request,
        Property $property,
        ReviewSummaryService $reviews,
        AnalyticsTracker $analytics,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing
    ): View {
        abort_unless(
            $property->is_published
            && ! in_array($property->status, ['inactive', 'unavailable', 'maintenance', 'archived'], true),
            404
        );

        $property->load([
            'publicVerifiedClaims',
            'amenities',
            'locationRecord',
            'images',
            'pointsOfInterest',
            'reviews' => fn ($query) => $query
                ->where('verified_stay', true)
                ->where('status', 'approved')
                ->with(['user', 'managementReplyBy'])
                ->latest()
                ->limit(12),
            'publicAccommodationTypes.ratePlans' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_public', true)
                ->with(['cancellationPolicy', 'paymentPolicy'])
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        $isFavourite = false;

        if ($request->user()) {
            $isFavourite = $request->user()->favourites()
                ->where('property_id', $property->getKey())
                ->exists();

            RecentlyViewedProperty::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->getKey(),
                    'property_id' => $property->getKey(),
                ],
                ['viewed_at' => now()]
            );

            $keepIds = RecentlyViewedProperty::query()
                ->where('user_id', $request->user()->getKey())
                ->latest('viewed_at')
                ->limit(50)
                ->pluck('id');

            if ($keepIds->isNotEmpty()) {
                RecentlyViewedProperty::query()
                    ->where('user_id', $request->user()->getKey())
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            }
        }

        $searchState = array_filter(
            (array) $request->session()->get('azari_stay_search', []),
            fn ($value) => $value !== null && $value !== ''
        );

        $rateOptions = collect();
        $hasStayDates = filled($searchState['check_in'] ?? null) && filled($searchState['check_out'] ?? null);

        if ($hasStayDates) {
            $checkIn = CarbonImmutable::parse($searchState['check_in'])->startOfDay();
            $checkOut = CarbonImmutable::parse($searchState['check_out'])->startOfDay();
            $adults = max(1, (int) ($searchState['adults'] ?? 1));
            $children = max(0, (int) ($searchState['children'] ?? 0));
            $rooms = max(1, (int) ($searchState['rooms'] ?? 1));

            foreach ($property->publicAccommodationTypes as $type) {
                foreach ($type->ratePlans as $plan) {
                    try {
                        $availability->assertRules(
                            $property,
                            $checkIn,
                            $checkOut,
                            $adults,
                            $children,
                            $rooms,
                            $type,
                            $plan
                        );

                        if (! $availability->availableForProperty(
                            $property,
                            $checkIn,
                            $checkOut,
                            $rooms,
                            $type->getKey()
                        )) {
                            continue;
                        }
                    } catch (ValidationException) {
                        continue;
                    }

                    $rateOptions->push([
                        'type' => $type,
                        'plan' => $plan,
                        'remaining' => $availability->availableQuantity($type, $checkIn, $checkOut),
                        'quote' => $pricing->quote(
                            $property,
                            $checkIn,
                            $checkOut,
                            [],
                            $type,
                            $plan,
                            $rooms
                        ),
                    ]);
                }
            }
        } else {
            foreach ($property->publicAccommodationTypes as $type) {
                foreach ($type->ratePlans as $plan) {
                    $base = (float) $type->base_rate;
                    $adjustment = (float) $plan->pricing_adjustment;
                    $fromRate = match ($plan->pricing_adjustment_type) {
                        'percentage' => $base + ($base * ($adjustment / 100)),
                        'fixed' => $base + $adjustment,
                        default => $base,
                    };

                    $rateOptions->push([
                        'type' => $type,
                        'plan' => $plan,
                        'remaining' => null,
                        'quote' => null,
                        'from_rate' => max(0, $fromRate),
                    ]);
                }
            }
        }

        $sessionId = $request->hasSession() ? $request->session()->getId() : 'no-session';
        $analytics->track('property_viewed', [
            'user_id' => $request->user()?->getKey(),
            'property_id' => $property->getKey(),
            'source' => 'property_page',
        ], hash('sha256', 'property-view|'.$sessionId.'|'.$property->getKey()));

        return view('public.properties.show', [
            'property' => $property,
            'reviewSummary' => $reviews->forProperty($property->getKey()),
            'isFavourite' => $isFavourite,
            'rateOptions' => $rateOptions,
            'searchState' => $searchState,
            'hasStayDates' => $hasStayDates,
        ]);
    }
}
