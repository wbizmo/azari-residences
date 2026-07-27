<?php

namespace App\Providers;

use App\Contracts\Communication\SmsProvider;
use App\Models\SiteSetting;
use App\Services\Communication\TwilioSmsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
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
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)->subject('Verify your Azari email address')->view('emails.premium', [
                'title' => 'Verify your email address',
                'preheader' => 'Complete your Azari account verification.',
                'lines' => ['Welcome to The Azari Residences.', 'Confirm this email address to secure your account and access your bookings.'],
                'actionLabel' => 'Verify email address',
                'actionUrl' => $url,
            ]);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            return (new MailMessage)->subject('Reset your Azari password')->view('emails.premium', [
                'title' => 'Reset your password',
                'preheader' => 'A password reset was requested for your Azari account.',
                'lines' => ['We received a request to reset your password.', 'Use the secure button below. If you did not request this, no action is required.'],
                'actionLabel' => 'Reset password',
                'actionUrl' => $url,
            ]);
        });

        Paginator::defaultView('vendor.pagination.azari-fancy');
        Paginator::defaultSimpleView('vendor.pagination.azari-fancy');

        View::composer([
            'components.public.layout', 'public.partials.navigation', 'public.partials.footer',
            'public.partials.drawer-root', 'layouts.user', 'user.*',
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
