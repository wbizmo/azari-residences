<?php

use App\Http\Controllers\Admin\TravelSupplierController;
use App\Http\Controllers\UserArea\TravelCatalogController;
use Illuminate\Support\Facades\Route;

// Separate from accommodation payment and booking controllers on purpose.
Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer'])
    ->prefix('account/travel')->name('user.travel.')->group(function (): void {
        Route::get('/', [TravelCatalogController::class, 'index'])->name('index');
        Route::post('/requests', [TravelCatalogController::class, 'store'])
            ->middleware('throttle:10,1')->name('store');
        Route::get('/requests/{travelRequest}', [TravelCatalogController::class, 'show'])
            ->name('show');
        Route::post('/requests/{travelRequest}/cancel', [TravelCatalogController::class, 'cancel'])
            ->middleware('throttle:10,1')->name('cancel');
    });

// Admin operations are hidden behind the existing administrator + step-up
// gates. Approval does not grant supplier access to traveler PII.
Route::middleware(['auth', 'auth.session', 'azari.staff', 'azari.staff:administrator'])
    ->prefix('azaridevadmin/travel')->name('azari.admin.travel.')->group(function (): void {
        Route::get('/', [TravelSupplierController::class, 'index'])->name('index');
        Route::post('/suppliers', [TravelSupplierController::class, 'store'])
            ->middleware('throttle:10,1')->name('suppliers.store');
        Route::post('/suppliers/{supplier}/approve', [TravelSupplierController::class, 'approve'])
            ->middleware(['azari.step-up', 'throttle:5,1'])->name('suppliers.approve');
        Route::post('/suppliers/{supplier}/pause', [TravelSupplierController::class, 'pause'])
            ->middleware(['azari.step-up', 'throttle:10,1'])->name('suppliers.pause');
        Route::post('/offers', [TravelSupplierController::class, 'storeOffer'])
            ->middleware('throttle:10,1')->name('offers.store');
        Route::post('/offers/{offer}/publish', [TravelSupplierController::class, 'publish'])
            ->middleware(['azari.step-up', 'throttle:10,1'])->name('offers.publish');
        Route::post('/offers/{offer}/unpublish', [TravelSupplierController::class, 'unpublish'])
            ->middleware(['azari.step-up', 'throttle:10,1'])->name('offers.unpublish');
        Route::post('/offers/{offer}/slots', [TravelSupplierController::class, 'storeSlot'])
            ->middleware('throttle:10,1')->name('slots.store');
        Route::post('/requests/{travelRequest}/review', [TravelSupplierController::class, 'review'])
            ->middleware(['azari.step-up', 'throttle:20,1'])->name('requests.review');
    });
