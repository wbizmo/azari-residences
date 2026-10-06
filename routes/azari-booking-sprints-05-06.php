<?php

use App\Http\Controllers\Admin\AzariBookingOperationsController;
use App\Http\Controllers\PublicSite\AzariAvailabilityController;
use App\Http\Controllers\PublicSite\AzariBookingFlowController;
use App\Http\Controllers\PublicSite\BookingOnboardingController;
use App\Http\Controllers\PublicSite\BookingVoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::post('/availability/{property}/hold', [AzariAvailabilityController::class, 'hold'])
        ->name('azari.availability.hold');

    Route::post('/availability/{property}/quote', [AzariAvailabilityController::class, 'quote'])
        ->middleware('throttle:60,1')
        ->name('azari.availability.quote');

    /*
    |--------------------------------------------------------------------------
    | Seamless booking onboarding
    |--------------------------------------------------------------------------
    |
    | The checkout entry point is intentionally public. An unregistered guest
    | can enter booking details first. The hold is then bound to either an
    | existing account or a newly-created account, email is verified with a
    | path-based OTP flow resumes the same hold after email verification.
    |
    */
    Route::get('/booking/checkout/{token}', [BookingOnboardingController::class, 'show'])
        ->name('azari.booking.checkout');

    Route::post('/booking/checkout/{token}/begin', [BookingOnboardingController::class, 'begin'])
        ->middleware('throttle:10,1')
        ->name('azari.booking.onboarding.begin');

    Route::middleware(['auth', 'auth.session', 'azari.customer'])->group(function (): void {
        Route::get('/booking/checkout/{token}/email', [BookingOnboardingController::class, 'email'])
            ->name('azari.booking.onboarding.email');

        Route::post('/booking/checkout/{token}/email/send', [BookingOnboardingController::class, 'sendEmailCode'])
            ->middleware('throttle:5,1')
            ->name('azari.booking.onboarding.email.send');

        Route::post('/booking/checkout/{token}/email/verify', [BookingOnboardingController::class, 'verifyEmailCode'])
            ->middleware('throttle:10,1')
            ->name('azari.booking.onboarding.email.verify');
    });

    Route::middleware([
        'auth',
        'auth.session',
        'verified',
        'azari.customer',
    ])->group(function (): void {
        Route::post('/booking/checkout/{token}/complete', [BookingOnboardingController::class, 'complete'])
            ->middleware('throttle:10,1')
            ->name('azari.booking.onboarding.complete');

        // Compatibility endpoint for existing clients that already submit the
        // booking form directly after authentication/KYC.
        Route::post('/booking', [AzariBookingFlowController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('azari.booking.store');

        Route::post('/booking/{reference}/confirm', [AzariBookingFlowController::class, 'confirm'])
            ->name('azari.booking.confirm');

        Route::post('/booking/{reference}/voucher', [BookingVoucherController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('azari.booking.voucher.store');

        Route::delete('/booking/{reference}/voucher', [BookingVoucherController::class, 'destroy'])
            ->name('azari.booking.voucher.destroy');
    });



    // Read-only review and summary remain reachable for already-created bookings.
    Route::get('/booking/{reference}/review', [AzariBookingFlowController::class, 'review'])
        ->name('azari.booking.review');

    Route::get('/booking/{reference}/summary', [AzariBookingFlowController::class, 'summary'])
        ->name('azari.booking.summary');

    Route::prefix('azaridevadmin')
        ->name('azari.admin.s56.')
        ->middleware(['auth', 'auth.session', 'azari.staff'])
        ->group(function (): void {
            Route::get('/availability-calendar', [AzariBookingOperationsController::class, 'calendar'])
                ->name('calendar');
            Route::post('/maintenance-periods', [AzariBookingOperationsController::class, 'storeMaintenance'])
                ->name('maintenance.store');
            Route::delete('/maintenance-periods/{maintenancePeriod}', [AzariBookingOperationsController::class, 'destroyMaintenance'])
                ->name('maintenance.destroy');
        });
});

Route::get('/azari/availability/results', [AzariAvailabilityController::class, 'index'])
    ->name('azari.availability.results');
