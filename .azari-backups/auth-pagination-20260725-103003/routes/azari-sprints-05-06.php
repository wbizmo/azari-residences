<?php

use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\AzariBookingCancellationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\PublicSite\BookingController;
use Illuminate\Support\Facades\Route;

// Replaced by routes/azari-public-completion.php
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{reference}', [BookingController::class, 'show'])->name('bookings.show');
Route::match(['get', 'post'], '/verify-booking', [BookingController::class, 'verify'])->name('bookings.verify');
Route::prefix('azari-admin')->name('azari.admin.')->middleware('azari.staff')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::put('/users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
    Route::put('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::get('/bookings', [BookingManagementController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/calendar', [BookingManagementController::class, 'calendar'])->name('bookings.calendar');
    Route::get('/bookings/{booking}', [BookingManagementController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/receipt', [BookingManagementController::class, 'receipt'])->name('bookings.receipt');
    Route::put('/bookings/{booking}/cancel', AzariBookingCancellationController::class)->name('bookings.cancel');
    Route::get('/bookings/{booking}/documents/{document}', [BookingManagementController::class, 'document'])->name('bookings.document');
});
