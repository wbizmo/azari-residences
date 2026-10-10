<?php

use App\Http\Controllers\Admin\DiningPartnerController;
use App\Http\Controllers\Admin\TripAssemblyRecoveryController;
use App\Http\Controllers\Admin\MobileDemandController;
use App\Http\Controllers\UserArea\DiningConciergeController;
use App\Http\Controllers\UserArea\TripAssemblyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','auth.session','verified','azari.customer'])
    ->prefix('account')->name('user.')->group(function (): void {
        Route::get('/dining',[DiningConciergeController::class,'index'])->name('dining.index');
        Route::post('/dining',[DiningConciergeController::class,'store'])
            ->middleware('throttle:10,1')->name('dining.store');
        Route::post('/dining/{diningRequest}/cancel',[DiningConciergeController::class,'cancel'])
            ->middleware('throttle:10,1')->name('dining.cancel');
        Route::post('/itineraries/{itinerary}/review-assembly',[TripAssemblyController::class,'store'])
            ->middleware('throttle:5,1')->name('itineraries.review-assembly');
    });

Route::middleware(['auth','auth.session','azari.staff','azari.staff:administrator'])
    ->prefix('azaridevadmin')->name('azari.admin.')->group(function (): void {
        Route::get('/dining',[DiningPartnerController::class,'index'])->name('dining.index');
        Route::post('/dining',[DiningPartnerController::class,'store'])
            ->middleware('throttle:10,1')->name('dining.store');
        Route::post('/dining/{partner}/publish',[DiningPartnerController::class,'publish'])
            ->middleware(['azari.step-up','throttle:5,1'])->name('dining.publish');
        Route::post('/dining/{partner}/pause',[DiningPartnerController::class,'pause'])
            ->middleware(['azari.step-up','throttle:10,1'])->name('dining.pause');
        Route::post('/dining/requests/{diningRequest}/decline',[DiningPartnerController::class,'decline'])
            ->middleware('throttle:20,1')->name('dining.decline');
        Route::post('/dining/requests/{diningRequest}/verify',[DiningPartnerController::class,'verifyReservation'])
            ->middleware(['azari.step-up','throttle:10,1'])->name('dining.verify');
        Route::get('/travel/assemblies',[TripAssemblyRecoveryController::class,'index'])
            ->name('travel.assemblies.index');
        Route::post('/travel/assemblies/{assembly}/review',[TripAssemblyRecoveryController::class,'review'])
            ->middleware(['azari.step-up','throttle:10,1'])->name('travel.assemblies.review');
        Route::get('/travel/mobile-demand',[MobileDemandController::class,'__invoke'])
            ->name('travel.mobile-demand');
    });
