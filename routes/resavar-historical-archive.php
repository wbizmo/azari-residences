<?php

use App\Http\Controllers\Admin\HistoricalTravelArchiveController as AdminArchiveController;
use App\Http\Controllers\UserArea\HistoricalTravelArchiveController as GuestArchiveController;
use Illuminate\Support\Facades\Route;

// Records created before the accommodation-only refocus remain available
// to their legitimate owner, without advertising or accepting new travel business.
Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer'])
    ->prefix('account/archive/travel')
    ->name('user.archive.travel.')
    ->group(function (): void {
        Route::get('/', [GuestArchiveController::class, 'index'])->name('index');
        Route::get('/requests/{travelRequest}', [GuestArchiveController::class, 'show'])->name('show');
    });

// Operators require the existing administrator gate. No POST/PUT/PATCH/DELETE
// routes are registered in the historical archive.
Route::middleware(['auth', 'auth.session', 'azari.staff', 'azari.staff:administrator'])
    ->prefix('azaridevadmin/archive/travel')
    ->name('azari.admin.archive.travel.')
    ->group(function (): void {
        Route::get('/', [AdminArchiveController::class, 'index'])->name('index');
    });
