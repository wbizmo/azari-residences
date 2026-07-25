<?php

use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\AzariBookingCancellationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\PublicSite\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{reference}', [BookingController::class, 'show'])->name('bookings.show');
Route::match(['get', 'post'], '/verify-booking', [BookingController::class, 'verify'])->name('bookings.verify');

Route::prefix('azari-admin')->name('azari.admin.')->middleware('azari.staff')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::put('/users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
    Route::put('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::get('/bookings', [BookingManagementController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/calendar', [BookingManagementController::class, 'calendar'])->name('bookings.calendar');
    Route::get('/bookings/{booking}', [BookingManagementController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/receipt', [BookingManagementController::class, 'receipt'])->name('bookings.receipt');
    Route::put('/bookings/{booking}/cancel', AzariBookingCancellationController::class)->name('bookings.cancel');
    Route::get('/bookings/{booking}/documents/{document}', [BookingManagementController::class, 'document'])->name('bookings.document');
});
