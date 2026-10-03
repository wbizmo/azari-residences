<?php

namespace App\Providers;

use App\Contracts\Communication\SmsProvider;
use App\Models\Booking;
use App\Models\GuestIdentityDocument;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayoutProfile;
use App\Models\Payment;
use App\Models\PropertyListing;
use App\Models\ServiceRequest;
use App\Models\SiteSetting;
use App\Models\SupportTicket;
use App\Models\UserIdentityDocument;
use App\Models\WithdrawalRequest;
use App\Observers\BookingObserver;
use App\Observers\GuestIdentityDocumentObserver;
use App\Observers\OwnerLedgerEntryObserver;
use App\Observers\OwnerPayoutProfileObserver;
use App\Observers\PaymentObserver;
use App\Observers\PropertyListingObserver;
use App\Observers\ServiceRequestObserver;
use App\Observers\SupportTicketObserver;
use App\Observers\UserIdentityDocumentObserver;
use App\Observers\WithdrawalRequestObserver;
use App\Services\Communication\TwilioSmsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
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
        // AZARI_TRANSACTIONAL_EMAIL_V102
        Booking::observe(BookingObserver::class);
        Payment::observe(PaymentObserver::class);
        ServiceRequest::observe(ServiceRequestObserver::class);
        SupportTicket::observe(SupportTicketObserver::class);
        PropertyListing::observe(PropertyListingObserver::class);
        OwnerPayoutProfile::observe(OwnerPayoutProfileObserver::class);
        WithdrawalRequest::observe(WithdrawalRequestObserver::class);
        OwnerLedgerEntry::observe(OwnerLedgerEntryObserver::class);
        UserIdentityDocument::observe(UserIdentityDocumentObserver::class);
        GuestIdentityDocument::observe(GuestIdentityDocumentObserver::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)->subject('Verify your Resavar email address')->view('emails.premium', [
                'title' => 'Verify your email address',
                'preheader' => 'Complete your Resavar account verification.',
                'lines' => ['Welcome to Resavar.', 'Confirm this email address to secure your account and access your bookings.'],
                'actionLabel' => 'Verify email address',
                'actionUrl' => $url,
            ]);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));

            return (new MailMessage)->subject('Reset your Resavar password')->view('emails.premium', [
                'title' => 'Reset your password',
                'preheader' => 'A password reset was requested for your Resavar account.',
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
            $view->with('siteSettings', [
                'site_name' => SiteSetting::valueFor('site_name', 'Resavar'),
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
