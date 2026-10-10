<?php

use App\Http\Controllers\Admin\CommercialInventoryController;
use App\Http\Controllers\Admin\ContentBlockController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Admin\PropertyClaimVerificationController;
use App\Http\Controllers\Admin\PropertyPhotoModerationController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\AzariAdminLoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\AccountDeletionRequestController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\PropertyController;
use App\Http\Controllers\Admin\ChannelConnectionController;
use App\Http\Controllers\PublicSite\ChannelCalendarController;
use App\Http\Controllers\PublicSite\DestinationController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Signed, provider-certified travel callback inbox. No booking/payment mutation on HTTP delivery.
Route::post('/webhooks/travel/{supplier}', \App\Http\Controllers\PhaseFour\TravelSupplierWebhookController::class)
    ->middleware('throttle:60,1')->name('travel.supplier.webhook');

// Authenticated, sandbox-gated inbox only. Provider-certified routes are separate.
Route::post('/webhooks/channels/{connection}', \App\Http\Controllers\PhaseThree\ChannelWebhookController::class)
    ->middleware('throttle:30,1')->name('channels.webhook');
Route::post('/language', \App\Http\Controllers\PhaseThree\LanguageController::class)
    ->middleware('throttle:15,1')->name('language.update');
Route::prefix('api/partners/v1')->name('partner.v1.')
    ->middleware('throttle:30,1')->group(function (): void {
        Route::get('/stays', [\App\Http\Controllers\PhaseThree\PartnerApiController::class, 'search'])->name('stays');
        Route::post('/intents', [\App\Http\Controllers\PhaseThree\PartnerApiController::class, 'intent'])->name('intents');
    });
Route::get('/', HomeController::class)->name('home');
Route::get('/health/live', [HealthController::class, 'live'])->middleware('throttle:120,1')->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->middleware('throttle:60,1')->name('health.ready');
// Availability routes are registered in azari-public-completion.php
Route::get('/residences/{property}', [PropertyController::class, 'show'])->name('properties.show');
Route::get('/destinations/{location:slug}', [DestinationController::class, 'show'])->name('destinations.show');
Route::get('/calendar/{token}.ics', ChannelCalendarController::class)->middleware('throttle:120,1')->name('channels.export');

Route::get('/account-deletion', [AccountDeletionRequestController::class, 'show'])
    ->name('account-deletion.show');
Route::post('/account-deletion', [AccountDeletionRequestController::class, 'store'])
    ->middleware('throttle:3,10')
    ->name('account-deletion.store');

Route::prefix('azaridevadmin')->group(function (): void {
    Route::get('/login', [AzariAdminLoginController::class, 'create'])
        ->middleware('throttle:20,1')
        ->name('azari.admin.login');
    Route::post('/login', [AzariAdminLoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('azari.admin.login.store');
});

Route::get('/azaridevadmin', DashboardController::class)->middleware(['auth.session', 'azari.staff'])->name('azari.admin.dashboard');

Route::prefix('azaridevadmin')->middleware(['auth.session', 'azari.staff'])->group(function (): void {
    Route::post('/logout', [AzariAdminLoginController::class, 'destroy'])->name('azari.admin.logout');

    Route::get('/branding', [SiteSettingController::class, 'edit'])->name('azari.admin.settings.edit');
    Route::put('/branding', [SiteSettingController::class, 'update'])
        ->middleware('azari.staff:administrator')
        ->name('azari.admin.settings.update');

    Route::get('/content', [ContentBlockController::class, 'index'])->name('azari.admin.content.index');
    Route::put('/content/{contentBlock}', [ContentBlockController::class, 'update'])
        ->middleware('azari.staff:administrator')
        ->name('azari.admin.content.update');

    Route::resource('properties', AdminPropertyController::class)
        ->except(['show', 'destroy'])
        ->names('azari.admin.properties');
    Route::patch('/properties/{property}/photos/{photo}', [PropertyPhotoModerationController::class, 'update'])
        ->middleware(['azari.staff:administrator', 'throttle:20,1'])
        ->name('azari.admin.properties.photos.update');
    Route::post('/properties/{property}/verified-claims', [PropertyClaimVerificationController::class, 'update'])
        ->middleware(['azari.staff:administrator', 'throttle:20,1'])
        ->name('azari.admin.properties.claims.update');

    Route::middleware('azari.staff:administrator')->group(function (): void {
        Route::get('/marketing-campaigns', [\App\Http\Controllers\PhaseThree\MarketingCampaignController::class, 'index'])
            ->name('azari.admin.marketing-campaigns.index');
        Route::post('/marketing-campaigns', [\App\Http\Controllers\PhaseThree\MarketingCampaignController::class, 'store'])
            ->middleware('throttle:10,1')->name('azari.admin.marketing-campaigns.store');
        Route::post('/marketing-campaigns/{campaign}/approve', [\App\Http\Controllers\PhaseThree\MarketingCampaignController::class, 'approve'])
            ->middleware(['azari.step-up','throttle:10,1'])->name('azari.admin.marketing-campaigns.approve');
        Route::post('/marketing-campaigns/{campaign}/pause', [\App\Http\Controllers\PhaseThree\MarketingCampaignController::class, 'pause'])
            ->middleware('throttle:10,1')->name('azari.admin.marketing-campaigns.pause');
    });
    Route::post('/properties/{property}/commission-agreements', \App\Http\Controllers\PhaseThree\CommissionAgreementController::class)
        ->middleware(['azari.staff:administrator','azari.step-up','throttle:10,1'])
        ->name('azari.admin.commission-agreements.approve');
    Route::get('/channels', [ChannelConnectionController::class, 'index'])->middleware('azari.permission:system-health.view')->name('azari.admin.channels.index');
    Route::get('/channels/events/operations', \App\Http\Controllers\Admin\ChannelEventOperationsController::class)
        ->middleware('azari.permission:system-health.view')->name('azari.admin.channels.events');
    Route::post('/channels', [ChannelConnectionController::class, 'store'])->middleware('azari.permission:system-health.manage')->name('azari.admin.channels.store');
    Route::put('/channels/{connection}', [ChannelConnectionController::class, 'update'])->middleware('azari.permission:system-health.manage')->name('azari.admin.channels.update');
    Route::post('/channels/{connection}/sync', [ChannelConnectionController::class, 'sync'])->middleware('azari.permission:system-health.manage')->name('azari.admin.channels.sync');
    Route::delete('/channels/{connection}', [ChannelConnectionController::class, 'destroy'])->middleware('azari.permission:system-health.manage')->name('azari.admin.channels.destroy');

    Route::get('/properties/{property}/commercial', [CommercialInventoryController::class, 'edit'])
        ->name('azari.admin.properties.commercial');
    Route::post('/properties/{property}/commercial/accommodations', [CommercialInventoryController::class, 'storeAccommodation'])
        ->name('azari.admin.properties.commercial.accommodations.store');
    Route::put('/properties/{property}/commercial/accommodations/{accommodationType}', [CommercialInventoryController::class, 'updateAccommodation'])
        ->name('azari.admin.properties.commercial.accommodations.update');
    Route::post('/properties/{property}/commercial/accommodations/{accommodationType}/rate-plans', [CommercialInventoryController::class, 'storeRatePlan'])
        ->name('azari.admin.properties.commercial.rate-plans.store');
    Route::put('/properties/{property}/commercial/accommodations/{accommodationType}/rate-plans/{ratePlan}', [CommercialInventoryController::class, 'updateRatePlan'])
        ->name('azari.admin.properties.commercial.rate-plans.update');

    Route::middleware('azari.admin')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('azari.admin.staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('azari.admin.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('azari.admin.staff.store');
        Route::patch('/staff/{user}/toggle', [StaffController::class, 'toggle'])->name('azari.admin.staff.toggle');
    });
});

Route::get('/dashboard', fn () => redirect()->route('user.dashboard'))
    ->middleware(['auth', 'auth.session', 'verified', 'azari.customer'])
    ->name('dashboard');

Route::middleware(['auth', 'auth.session', 'azari.customer'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

require __DIR__.'/azari-sprints-3-4.php';

require __DIR__.'/azari-sprints-05-06.php';
require __DIR__.'/azari-booking-sprints-05-06.php';

require __DIR__.'/azari-public-completion.php';
require __DIR__.'/azari-sprints-07-08.php';

require __DIR__.'/azari-sprints-09-12.php';


require __DIR__.'/azari-sprints-13-16.php';

require __DIR__.'/azari-property-owner-extension.php';
require __DIR__.'/resavar-phase-two.php';
require __DIR__.'/resavar-phase-four.php';

/*
|--------------------------------------------------------------------------
| AZARI_PUBLIC_STATUS_ROUTE_V1
|--------------------------------------------------------------------------
|
| Public-safe operational status page. The controller reads only the
| sanitized snapshot generated by the status collector.
|
*/

\Illuminate\Support\Facades\Route::get(
    '/status',
    \App\Http\Controllers\PublicStatusController::class
)->name('public.status');



/*
|--------------------------------------------------------------------------
| AZARI_PUBLIC_STATUS_DATA_ROUTE_V1
|--------------------------------------------------------------------------
|
| Public-safe JSON used only by the /status presentation.
|
*/

\Illuminate\Support\Facades\Route::get(
    '/status/data',
    [
        \App\Http\Controllers\PublicStatusController::class,
        'data',
    ]
)->name('public.status.data');
