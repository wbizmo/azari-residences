<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Property;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'featuredResidences' => Property::query()
                ->with('amenities')
                ->where('is_published', true)
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->latest()
                ->limit(6)
                ->get(),
            'content' => [
                'hero_eyebrow' => ContentBlock::content('home.hero.eyebrow', 'Private serviced residences'),
                'hero_title' => ContentBlock::content('home.hero.title', 'Exceptional stays, thoughtfully managed.'),
                'hero_body' => ContentBlock::content('home.hero.body', 'Discover private, fully serviced residences across our operating destinations.'),
                'about_title' => ContentBlock::content('home.about.title', 'A considered collection of residences.'),
                'about_body' => ContentBlock::content('home.about.body', 'Every property is selected, prepared and managed to a consistent hospitality standard.'),
                'featured_title' => ContentBlock::content('home.featured.title', 'Featured residences'),
                'services_title' => ContentBlock::content('home.services.title', 'Hospitality beyond the front door.'),
            ],
            'siteName' => SiteSetting::valueFor('site_name', 'Azari Residences'),
            'operatingRegions' => SiteSetting::valueFor('operating_regions', 'Nigeria and Rwanda'),
        ]);
    }
}
