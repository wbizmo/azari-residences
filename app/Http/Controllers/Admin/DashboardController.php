<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Property;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Admin\HospitalityOperationsService;
use App\Services\Analytics\MarketplaceAnalyticsService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        HospitalityOperationsService $operations,
        MarketplaceAnalyticsService $analytics
    ): View {
        return view('admin.dashboard', [
            'userCount' => User::query()->count(),
            'adminCount' => User::query()
                ->where(function ($query): void {
                    $query->where('is_admin', true)
                        ->orWhere('staff_role', 'administrator');
                })
                ->count(),
            'propertyCount' => Property::query()->count(),
            'featuredCount' => Property::query()->where('is_featured', true)->count(),
            'staffCount' => User::query()->whereNotNull('staff_role')->count(),
            'contentCount' => ContentBlock::query()->count(),
            'logoConfigured' => filled(SiteSetting::valueFor('site_logo')),
            'operations' => $operations->today(),
            'operationQueues' => $operations->queues(),
            'analytics' => $analytics->summary(),
        ]);
    }
}
