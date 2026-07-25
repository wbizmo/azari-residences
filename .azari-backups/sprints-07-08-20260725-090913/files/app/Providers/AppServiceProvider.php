<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;

use App\Contracts\Communication\SmsProvider;
use App\Models\SiteSetting;
use App\Services\Communication\TwilioSmsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsProvider::class, TwilioSmsService::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.azari');
        // site-setting-public-composer
        View::composer([
            'components.public.layout',
            'public.partials.navigation',
            'public.partials.footer',
            'public.partials.drawer-root',
        ], function ($view): void {
            $logo = SiteSetting::valueFor('site_logo');
            $favicon = SiteSetting::valueFor('favicon');

            $view->with('siteSettings', [
                'site_name' => SiteSetting::valueFor('site_name', 'Azari Residences'),
                'logo_url' => $logo ? Storage::url($logo) : null,
                'favicon_url' => $favicon ? Storage::url($favicon) : null,
            ]);
        });
        date_default_timezone_set(config('azari.timezone', 'Africa/Lagos'));

        $applicationUrl = rtrim((string) config('app.url'), '/');

        if ($applicationUrl !== '') {
            URL::forceRootUrl($applicationUrl);

            if (str_starts_with($applicationUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
