<?php

use App\Http\Controllers\User\OwnerBookingMessageController;
use App\Http\Controllers\User\OwnerCommercialInventoryController;
use App\Http\Controllers\User\OwnerPhaseTwoController;
use App\Http\Controllers\UserArea\PhaseTwoGuestController;
use App\Http\Controllers\UserArea\ReviewController;
use App\Http\Controllers\UserArea\ReviewInteractionController;
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
            Route::delete('/staff/{membership}', [OwnerPhaseTwoController::class, 'revoke'])->name('staff.revoke');

            Route::get('/operations', [OwnerPhaseTwoController::class, 'operations'])->name('operations');
            Route::post('/operations/tasks', [OwnerPhaseTwoController::class, 'createTask'])->name('operations.tasks.store');
            Route::patch('/operations/tasks/{task}', [OwnerPhaseTwoController::class, 'updateTask'])->name('operations.tasks.update');

            Route::post('/commercial/{accommodationType}/calendar/preview', [OwnerCommercialInventoryController::class, 'previewBulkUpdate'])->name('calendar.preview');
            Route::post('/commercial/calendar-changes/{log}/undo', [OwnerCommercialInventoryController::class, 'undoBulkUpdate'])->name('calendar.undo');

            Route::post('/bookings/{booking}/room-ready', [OwnerPhaseTwoController::class, 'markRoomReady'])->name('room-ready');
            Route::delete('/bookings/{booking}/room-ready', [OwnerPhaseTwoController::class, 'revokeRoomReady'])->name('room-ready.revoke');

            Route::get('/reviews', [OwnerPhaseTwoController::class, 'reviews'])->name('reviews');
            Route::post('/reviews/{review}/reply', [OwnerPhaseTwoController::class, 'replyReview'])->name('reviews.reply');

            Route::get('/messages', [OwnerPhaseTwoController::class, 'conversations'])->name('messages');
            Route::get('/messages/{conversation}', [OwnerBookingMessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{conversation}', [OwnerBookingMessageController::class, 'store'])
                ->middleware('throttle:30,1')->name('messages.store');
        });

    Route::prefix('account/bookings/{reference}')->name('user.bookings.phase2.')->group(function (): void {
        Route::get('/messages', [PhaseTwoGuestController::class, 'messages'])->name('messages');
        Route::post('/messages', [PhaseTwoGuestController::class, 'sendMessage'])
            ->middleware('throttle:30,1')->name('messages.store');
        Route::get('/messages/poll', [PhaseTwoGuestController::class, 'pollMessages'])->name('messages.poll');
        Route::get('/messages/attachments/{message}', [PhaseTwoGuestController::class, 'messageAttachment'])->name('messages.attachment');

        Route::get('/arrival', [PhaseTwoGuestController::class, 'arrival'])->name('arrival');
        Route::patch('/arrival', [PhaseTwoGuestController::class, 'updateArrival'])->name('arrival.update');
        Route::post('/arrival/check-in', [PhaseTwoGuestController::class, 'selfCheckIn'])
            ->middleware('throttle:5,10')->name('arrival.check-in');
    });

    Route::patch('/account/bookings/{booking}/review', [ReviewController::class, 'update'])->name('user.reviews.update');
    Route::post('/reviews/{review}/helpful', [ReviewInteractionController::class, 'helpful'])->middleware('throttle:30,1')->name('reviews.helpful');
    Route::post('/reviews/{review}/report', [ReviewInteractionController::class, 'report'])->middleware('throttle:10,10')->name('reviews.report');

    Route::post('/account/push-subscriptions', [PhaseTwoGuestController::class, 'subscribePush'])
        ->middleware('throttle:20,1')->name('user.push.subscribe');
    Route::delete('/account/push-subscriptions', [PhaseTwoGuestController::class, 'unsubscribePush'])
        ->middleware('throttle:20,1')->name('user.push.unsubscribe');
});
