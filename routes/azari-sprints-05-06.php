<?php

use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\AzariBookingCancellationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\PublicSite\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{reference}', [BookingController::class, 'show'])->name('bookings.show');
Route::match(['get', 'post'], '/verify-booking', [BookingController::class, 'verify'])->name('bookings.verify');

Route::prefix('azaridevadmin')->name('azari.admin.')->middleware('azari.staff')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::put('/users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
    Route::put('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::get('/bookings', [BookingManagementController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/calendar', [BookingManagementController::class, 'calendar'])->name('bookings.calendar');
    Route::get('/bookings/{booking}', [BookingManagementController::class, 'show'])->name('bookings.show');
    Route::put('/bookings/{booking}/status', [BookingManagementController::class, 'transition'])->name('bookings.status');
    Route::post('/bookings/{booking}/notes', [BookingManagementController::class, 'storeNote'])->name('bookings.notes.store');
    Route::put('/bookings/{booking}/modifications/{modification}/review', [BookingManagementController::class, 'reviewModification'])
        ->middleware('azari.permission:bookings.edit')
        ->name('bookings.modifications.review');
    Route::get('/bookings/{booking}/receipt', [BookingManagementController::class, 'receipt'])->name('bookings.receipt');
    Route::post('/bookings/{booking}/modifications/{modification}/offer', [BookingManagementController::class, 'offerDateAmendment'])
        ->middleware('azari.permission:bookings.edit')->name('bookings.modifications.offer');
    Route::post('/bookings/{booking}/no-show', [BookingManagementController::class, 'markNoShow'])
        ->middleware('azari.permission:bookings.edit')->middleware('azari.step-up')->name('bookings.no-show');
    Route::get('/bookings/{booking}/cancellation-quote', [BookingManagementController::class, 'cancellationQuote'])
        ->middleware('azari.permission:bookings.view')->name('bookings.cancellation-quote');
    Route::post('/bookings/{booking}/cancellation-overrides', [BookingManagementController::class, 'requestCancellationOverride'])
        ->middleware('azari.permission:bookings.edit')
        ->middleware('azari.step-up')
        ->middleware('throttle:5,10')
        ->name('bookings.cancellation-overrides.store');
    Route::post('/bookings/{booking}/cancellation-overrides/{override}/review', [BookingManagementController::class, 'reviewCancellationOverride'])
        ->middleware('azari.admin')
        ->middleware('azari.permission:payments.manage')
        ->middleware('azari.step-up')
        ->middleware('throttle:10,10')
        ->name('bookings.cancellation-overrides.review');
    Route::put('/bookings/{booking}/cancel', AzariBookingCancellationController::class)
        ->middleware('azari.permission:bookings.edit')->middleware('azari.step-up')->name('bookings.cancel');
});
