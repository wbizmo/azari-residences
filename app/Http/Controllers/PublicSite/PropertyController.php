<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\RecentlyViewedProperty;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Reviews\ReviewSummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function show(
        Request $request,
        Property $property,
        ReviewSummaryService $reviews,
        AnalyticsTracker $analytics
    ): View {
        abort_unless($property->is_published, 404);

        $property->load([
            'amenities',
            'locationRecord',
            'images',
            'reviews' => fn ($query) => $query
                ->with(['user', 'managementReplyBy'])
                ->latest()
                ->limit(12),
            'publicAccommodationTypes.ratePlans' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_public', true)
                ->with(['cancellationPolicy', 'paymentPolicy']),
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
        ]);
    }
}
