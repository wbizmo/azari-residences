<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\HomepageSection;
use App\Models\Location;
use App\Models\Property;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'homepagePromotion' => Promotion::query()
                ->visible()
                ->where('show_on_homepage', true)
                ->where('type', 'promotion')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->latest('updated_at')
                ->first(),

            'settings' => Cache::remember('public:site-settings', now()->addMinutes(5), fn () => SiteSetting::query()->pluck('value', 'key')->all()),

            'content' => Cache::remember('public:content-blocks', now()->addMinutes(5), fn () => ContentBlock::query()->where('is_active', true)->pluck('value', 'key')->all()),

            'featuredResidences' => Property::query()
                ->with(['locationRecord', 'roomType', 'amenities', 'photoModerations'])
                ->where('is_published', true)
                ->where('is_featured', true)
                ->where('status', '!=', 'inactive')
                ->orderBy('sort_order')
                ->limit(6)
                ->get(),

            'locations' => Location::query()
                ->where('is_active', true)
                ->whereHas(
                    'properties',
                    fn ($query) => $query->where('is_published', true)
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->whereHas(
                    'properties',
                    fn ($query) => $query->where('is_published', true)
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            'homepageSections' => HomepageSection::query()
                ->where('is_active', true)
                ->where('status', 'published')
                ->orderBy('sort_order')
                ->get()
                ->keyBy('key'),

            'popularLocations' => Location::query()
                ->withCount(['properties as published_properties_count' => fn ($query) =>
                    $query->where('is_published', true)
                ])
                ->where('is_active', true)
                ->whereHas('properties', fn ($query) => $query->where('is_published', true))
                ->orderByDesc('published_properties_count')
                ->orderBy('sort_order')
                ->limit(6)
                ->get(),

            'newResidences' => Property::query()
                ->with(['locationRecord', 'roomType', 'photoModerations'])
                ->where('is_published', true)
                ->whereNotIn('status', ['inactive', 'unavailable', 'maintenance', 'archived'])
                ->latest('id')
                ->limit(4)
                ->get(),

            'propertyTypeStats' => Property::query()
                ->selectRaw('property_type, COUNT(*) as properties_count')
                ->where('is_published', true)
                ->whereNotNull('property_type')
                ->groupBy('property_type')
                ->orderByDesc('properties_count')
                ->limit(6)
                ->get(),
        ]);
    }
}