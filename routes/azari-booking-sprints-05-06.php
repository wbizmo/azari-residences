<?php

use App\Http\Controllers\Admin\AzariBookingOperationsController;
use App\Http\Controllers\PublicSite\AzariAvailabilityController;
use App\Http\Controllers\PublicSite\AzariBookingFlowController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\BookingVoucherController;

Route::middleware('web')->group(function () {
    Route::post('/availability/{property}/hold', [AzariAvailabilityController::class, 'hold'])->name('azari.availability.hold');
    Route::post('/availability/{property}/quote', [AzariAvailabilityController::class, 'quote'])->middleware('throttle:60,1')->name('azari.availability.quote');

    Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer', 'azari.identity.verified'])->group(function (): void {
        Route::get('/booking/checkout/{token}', [AzariBookingFlowController::class, 'checkout'])->name('azari.booking.checkout');
        Route::post('/booking', [AzariBookingFlowController::class, 'store'])->middleware('throttle:20,1')->name('azari.booking.store');
        Route::post('/booking/{reference}/confirm', [AzariBookingFlowController::class, 'confirm'])->name('azari.booking.confirm');
        Route::post('/booking/{reference}/voucher', [BookingVoucherController::class, 'store'])->middleware('throttle:10,1')->name('azari.booking.voucher.store');
        Route::delete('/booking/{reference}/voucher', [BookingVoucherController::class, 'destroy'])->name('azari.booking.voucher.destroy');
    });

    // Keep read-only access to already-created legacy guest bookings. New bookings and
    // payment progression require an authenticated, verified Azari customer above.
    Route::get('/booking/{reference}/review', [AzariBookingFlowController::class, 'review'])->name('azari.booking.review');
    Route::get('/booking/{reference}/summary', [AzariBookingFlowController::class, 'summary'])->name('azari.booking.summary');

    Route::prefix('azari-admin')->name('azari.admin.s56.')->middleware(['auth', 'auth.session', 'azari.staff'])->group(function () {
        Route::get('/availability-calendar', [AzariBookingOperationsController::class, 'calendar'])->name('calendar');
        Route::post('/maintenance-periods', [AzariBookingOperationsController::class, 'storeMaintenance'])->name('maintenance.store');
        Route::delete('/maintenance-periods/{maintenancePeriod}', [AzariBookingOperationsController::class, 'destroyMaintenance'])->name('maintenance.destroy');
    });
});

Route::get('/azari/availability/results', [AzariAvailabilityController::class, 'index'])
    ->name('azari.availability.results');
