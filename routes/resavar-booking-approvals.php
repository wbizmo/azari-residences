<?php

use App\Http\Controllers\AccommodationBookingReviewController;
use Illuminate\Support\Facades\Route;

// Review routes are separate from the payment and instant-booking controllers.
// The service enforces row-level property ownership inside the transaction.
Route::prefix('user/property-centre/booking-requests')
    ->name('user.owner.booking-requests.')
    ->middleware(['auth', 'auth.session', 'verified', 'azari.customer', 'throttle:30,1'])
    ->group(function (): void {
        Route::get('/', [AccommodationBookingReviewController::class, 'index'])->name('index');
        Route::post('/{booking}/review', [AccommodationBookingReviewController::class, 'underReview'])->name('review');
        Route::post('/{booking}/approve', [AccommodationBookingReviewController::class, 'approve'])->name('approve');
        Route::post('/{booking}/decline', [AccommodationBookingReviewController::class, 'decline'])->name('decline');
        Route::post('/{booking}/propose', [AccommodationBookingReviewController::class, 'propose'])->name('propose');
    });

Route::prefix('azaridevadmin/booking-requests')
    ->name('azari.admin.booking-requests.')
    ->middleware([
        'auth', 'auth.session', 'verified', 'azari.staff',
        'azari.permission:bookings.edit', 'throttle:30,1',
    ])
    ->group(function (): void {
        Route::get('/', [AccommodationBookingReviewController::class, 'index'])->name('index');
        Route::post('/{booking}/review', [AccommodationBookingReviewController::class, 'underReview'])->name('review');
        Route::post('/{booking}/approve', [AccommodationBookingReviewController::class, 'approve'])->name('approve');
        Route::post('/{booking}/decline', [AccommodationBookingReviewController::class, 'decline'])->name('decline');
        Route::post('/{booking}/propose', [AccommodationBookingReviewController::class, 'propose'])->name('propose');
    });
