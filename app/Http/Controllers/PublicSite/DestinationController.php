<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class DestinationController extends Controller
{
    public function show(Location $location): View
    {
        abort_unless($location->is_active, 404);

        $properties = Property::query()
            ->with(['roomType', 'locationRecord'])
            ->where('location_id', $location->getKey())
            ->where('is_published', true)
            ->whereNotIn('status', ['inactive', 'archived'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->paginate(18);

        $nearby = Cache::remember('public:destinations:related:'.$location->getKey(), now()->addMinutes(15), fn () =>
            Location::query()->where('is_active', true)->whereKeyNot($location->getKey())
                ->where('country', $location->country)->whereHas('properties', fn ($q) => $q->where('is_published', true))
                ->orderBy('sort_order')->limit(6)->get()
        );

        return view('public.destinations.show', compact('location', 'properties', 'nearby'));
    }
}
