<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'settings' => SiteSetting::query()->pluck('value', 'key'),

            'content' => ContentBlock::query()->pluck('content', 'key'),

            'featuredResidences' => Property::query()
                ->with(['locationRecord', 'roomType', 'amenities'])
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
        ]);
    }
}