<?php

use App\Http\Controllers\Admin\AzariBookingOperationsController;
use App\Http\Controllers\PublicSite\AzariAvailabilityController;
use App\Http\Controllers\PublicSite\AzariBookingFlowController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/availability/{property}/hold', [AzariAvailabilityController::class, 'hold'])->name('azari.availability.hold');
    Route::post('/availability/{property}/quote', [AzariAvailabilityController::class, 'quote'])->middleware('throttle:60,1')->name('azari.availability.quote');
    Route::get('/booking/checkout/{token}', [AzariBookingFlowController::class, 'checkout'])->name('azari.booking.checkout');
    Route::post('/booking', [AzariBookingFlowController::class, 'store'])->middleware('throttle:20,1')->name('azari.booking.store');
    Route::get('/booking/{reference}/review', [AzariBookingFlowController::class, 'review'])->name('azari.booking.review');
    Route::post('/booking/{reference}/confirm', [AzariBookingFlowController::class, 'confirm'])->name('azari.booking.confirm');
    Route::get('/booking/{reference}/summary', [AzariBookingFlowController::class, 'summary'])->name('azari.booking.summary');

    Route::prefix('azari-admin')->name('azari.admin.s56.')->middleware(['auth', 'azari.staff'])->group(function () {
        Route::get('/booking-operations', [AzariBookingOperationsController::class, 'index'])->name('bookings.index');
        Route::get('/availability-calendar', [AzariBookingOperationsController::class, 'calendar'])->name('calendar');
        Route::put('/booking-operations/{booking}/transition', [AzariBookingOperationsController::class, 'transition'])->name('bookings.transition');
        Route::post('/maintenance-periods', [AzariBookingOperationsController::class, 'storeMaintenance'])->name('maintenance.store');
        Route::delete('/maintenance-periods/{maintenancePeriod}', [AzariBookingOperationsController::class, 'destroyMaintenance'])->name('maintenance.destroy');
    });
});

Route::get('/azari/availability/results', [AzariAvailabilityController::class, 'index'])
    ->name('azari.availability.results');
