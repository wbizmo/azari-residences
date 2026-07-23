<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Property;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'propertyCount' => Property::query()->count(),
            'featuredCount' => Property::query()->where('is_featured', true)->count(),
            'staffCount' => User::query()->whereNotNull('staff_role')->count(),
            'contentCount' => ContentBlock::query()->count(),
            'logoConfigured' => filled(SiteSetting::valueFor('site_logo')),
        ]);
    }
}
