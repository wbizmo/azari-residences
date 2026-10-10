<?php

use App\Http\Controllers\User\OwnerBookingMessageController;
use App\Http\Controllers\User\OwnerCommercialInventoryController;
use App\Http\Controllers\User\OwnerPhaseTwoController;
use App\Http\Controllers\UserArea\PhaseTwoGuestController;
use App\Http\Controllers\UserArea\ReviewController;
use App\Http\Controllers\UserArea\ReviewHelpfulController;
use App\Http\Controllers\UserArea\BookingShareController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer'])->group(function (): void {
    Route::get('/property-staff/invitations/{token}/accept', [OwnerPhaseTwoController::class, 'accept'])
        ->middleware('throttle:20,1')
        ->name('user.owner.staff.accept');

    Route::prefix('user/property-centre/properties/{property}')
        ->name('user.owner.phase2.')
        ->group(function (): void {
            Route::get('/staff', [OwnerPhaseTwoController::class, 'staff'])->name('staff');
            Route::post('/staff/invitations', [OwnerPhaseTwoController::class, 'invite'])
                ->middleware('throttle:10,10')->name('staff.invite');
            Route::patch('/staff/{membership}', [OwnerPhaseTwoController::class, 'updateStaffRole'])
                ->middleware('throttle:15,1')->name('staff.update');
            Route::delete('/staff/{membership}', [OwnerPhaseTwoController::class, 'revoke'])->name('staff.revoke');

            Route::get('/operations', [OwnerPhaseTwoController::class, 'operations'])->name('operations');
            Route::post('/operations/tasks', [OwnerPhaseTwoController::class, 'createTask'])->name('operations.tasks.store');
            Route::patch('/operations/tasks/{task}', [OwnerPhaseTwoController::class, 'updateTask'])->name('operations.tasks.update');
            Route::get('/operations/tasks/{task}/evidence', [OwnerPhaseTwoController::class, 'taskEvidence'])
                ->middleware('throttle:60,1')->name('operations.tasks.evidence');

            Route::post('/commercial/{accommodationType}/calendar/preview', [OwnerCommercialInventoryController::class, 'previewBulkUpdate'])->name('calendar.preview');
            Route::post('/commercial/calendar-changes/{log}/undo', [OwnerCommercialInventoryController::class, 'undoBulkUpdate'])->name('calendar.undo');

            Route::get('/reviews', [OwnerPhaseTwoController::class, 'reviews'])->name('reviews');
            Route::post('/reviews/{review}/reply', [OwnerPhaseTwoController::class, 'replyReview'])->name('reviews.reply');

            Route::get('/messages', [OwnerPhaseTwoController::class, 'conversations'])->name('messages');
            Route::get('/messages/{conversation}', [OwnerBookingMessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{conversation}', [OwnerBookingMessageController::class, 'store'])
                ->middleware('throttle:30,1')->name('messages.store');
            Route::get('/messages/{conversation}/attachments/{message}', [OwnerBookingMessageController::class, 'attachment'])
                ->middleware('throttle:60,1')->name('messages.attachment');
        });

    Route::prefix('account/bookings/{reference}')->name('user.bookings.phase2.')->group(function (): void {
        Route::get('/messages', [PhaseTwoGuestController::class, 'messages'])->name('messages');
        Route::post('/messages', [PhaseTwoGuestController::class, 'sendMessage'])
            ->middleware('throttle:30,1')->name('messages.store');
        Route::get('/messages/attachments/{message}', [PhaseTwoGuestController::class, 'messageAttachment'])->name('messages.attachment');

        Route::get('/arrival', [PhaseTwoGuestController::class, 'arrival'])->name('arrival');
        Route::patch('/arrival', [PhaseTwoGuestController::class, 'updateArrival'])->name('arrival.update');
        Route::post('/arrival/check-in', [PhaseTwoGuestController::class, 'selfCheckIn'])
            ->middleware('throttle:5,10')->name('arrival.check-in');
    });

    Route::post('/account/bookings/{reference}/share', [BookingShareController::class, 'create'])
        ->middleware('throttle:5,60')->name('user.bookings.share.create');
    Route::delete('/account/bookings/{reference}/share', [BookingShareController::class, 'revoke'])
        ->middleware('throttle:10,60')->name('user.bookings.share.revoke');

    Route::post('/reviews/{review}/helpful', [ReviewHelpfulController::class, 'store'])
        ->middleware('throttle:20,1')->name('user.reviews.helpful');

    Route::patch('/account/bookings/{booking}/review', [ReviewController::class, 'update'])->name('user.reviews.update');
    Route::post('/account/bookings/{booking}/review/appeal', [ReviewController::class, 'appeal'])
        ->middleware('throttle:3,60')->name('user.reviews.appeal');
});
