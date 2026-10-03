<?php

use App\Http\Controllers\Admin\AzariBookingOperationsController;
use App\Http\Controllers\PublicSite\AzariAvailabilityController;
use App\Http\Controllers\PublicSite\AzariBookingFlowController;
use App\Http\Controllers\PublicSite\BookingOnboardingController;
use App\Http\Controllers\PublicSite\PublicGuestVerificationController;
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
    | path-based OTP flow, Dojah KYC runs, and the same hold resumes.
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
        'azari.identity.verified',
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


    /*
    |--------------------------------------------------------------------------
    | External adult guest verification
    |--------------------------------------------------------------------------
    |
    | URLs are path-only by design:
    | /guest-verification/{booking-reference}/{adult-position}
    |
    | The route itself never reveals the guest's personal details. The invited
    | adult must prove control of the email supplied by the booker before KYC
    | or optional account linking/creation is exposed.
    */
    Route::get('/guest-verification/{reference}/{position}', [PublicGuestVerificationController::class, 'show'])
        ->whereNumber('position')
        ->name('guest-verification.show');

    Route::post('/guest-verification/{reference}/{position}/code/send', [PublicGuestVerificationController::class, 'sendCode'])
        ->whereNumber('position')
        ->middleware('throttle:5,1')
        ->name('guest-verification.code.send');

    Route::post('/guest-verification/{reference}/{position}/code/verify', [PublicGuestVerificationController::class, 'verifyCode'])
        ->whereNumber('position')
        ->middleware('throttle:10,1')
        ->name('guest-verification.code.verify');

    Route::get('/guest-verification/{reference}/{position}/status', [PublicGuestVerificationController::class, 'status'])
        ->whereNumber('position')
        ->middleware('throttle:60,1')
        ->name('guest-verification.status');

    Route::post('/guest-verification/{reference}/{position}/account/create', [PublicGuestVerificationController::class, 'createAccount'])
        ->whereNumber('position')
        ->middleware('throttle:5,1')
        ->name('guest-verification.account.create');

    Route::post('/guest-verification/{reference}/{position}/account/link', [PublicGuestVerificationController::class, 'linkAccount'])
        ->whereNumber('position')
        ->middleware('throttle:10,1')
        ->name('guest-verification.account.link');

    // Read-only review and summary remain reachable for already-created
    // bookings. New booking/payment progression is authenticated and KYC-gated.
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
