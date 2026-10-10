<?php

namespace App\Http\Controllers\PhaseThree;

use App\Http\Controllers\Controller;
use App\Models\RecentlyViewedProperty;
use App\Services\PhaseThree\PrivacyAwareRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

final class RecommendationController extends Controller
{
    public function index(Request $request, PrivacyAwareRecommendationService $service): JsonResponse|View
    {
        $data = $request->validate([
            'check_in' => ['required','date','after_or_equal:today'],
            'check_out' => ['required','date','after:check_in'],
            'adults' => ['required','integer','min:1','max:12'],
            'children' => ['sometimes','integer','min:0','max:8'],
            'rooms' => ['sometimes','integer','min:1','max:5'],
            'destination' => ['sometimes','string','max:120'],
            'amenities' => ['sometimes','array','max:20'],
            'amenities.*' => ['integer','min:1'],
        ]);
        try {
            $items = $service->forGuest($request->user(), $data);
        } catch (\Throwable $e) {
            report($e);
            // Personalization cannot make the ordinary search journey fail.
            if (! $request->expectsJson()) {
                return view('user.recommendations', ['items' => [], 'personalized' => false]);
            }
            return response()->json(['items' => [], 'fallback_url' => route('home'), 'personalized' => false]);
        }
        $personalized = ! (bool) $request->user()->personalization_opt_out;
        if (! $request->expectsJson()) {
            return view('user.recommendations', compact('items', 'personalized'));
        }
        return response()->json(['items' => $items, 'personalized' => $personalized]);
    }

    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['personalization_opt_out' => ['required','boolean']]);
        $optOut = (bool) $data['personalization_opt_out'];
        $request->user()->forceFill(['personalization_opt_out' => $optOut])->save();
        if ($optOut) {
            RecentlyViewedProperty::query()->where('user_id', $request->user()->getKey())->delete();
        }
        return back()->with('success', $optOut
            ? 'Personalization disabled and recent-property history cleared.'
            : 'Personalization enabled.');
    }

    public function clear(Request $request): RedirectResponse
    {
        RecentlyViewedProperty::query()->where('user_id', $request->user()->getKey())->delete();
        return back()->with('success', 'Recent-property history cleared.');
    }
}
