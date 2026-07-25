<?php

namespace App\Providers;

use App\Contracts\Communication\SmsProvider;
use App\Models\SiteSetting;
use App\Services\Communication\TwilioSmsService;
use Illuminate\Pagination\Paginator;
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

        View::composer([
            'components.public.layout',
            'public.partials.navigation',
            'public.partials.footer',
            'public.partials.drawer-root',
            'layouts.user',
            'user.*',
        ], function ($view): void {
            $primary = SiteSetting::valueFor('site_logo');
            $light = SiteSetting::valueFor('light_logo') ?: $primary;
            $dark = SiteSetting::valueFor('dark_logo') ?: $primary;
            $favicon = SiteSetting::valueFor('favicon');

            $view->with('siteSettings', [
                'site_name' => SiteSetting::valueFor('site_name', 'Azari Residences'),
                'logo_url' => $primary ? Storage::url($primary) : null,
                'light_logo_url' => $light ? Storage::url($light) : null,
                'dark_logo_url' => $dark ? Storage::url($dark) : null,
                'favicon_url' => $favicon ? Storage::url($favicon) : null,
            ]);
        });

        date_default_timezone_set(config('azari.timezone', 'Africa/Lagos'));
        $applicationUrl = rtrim((string) config('app.url'), '/');
        if ($applicationUrl !== '') {
            URL::forceRootUrl($applicationUrl);
            if (str_starts_with($applicationUrl, 'https://')) URL::forceScheme('https');
        }
    }
}
