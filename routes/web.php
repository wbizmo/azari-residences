<?php

use App\Http\Controllers\Admin\ContentBlockController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\AzariAdminLoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\PropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
// Availability routes are registered in azari-public-completion.php
Route::get('/residences/{property}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/favicon.svg', fn () => redirect('/images/azari-favicon.png'))->name('public.favicon');

Route::prefix('azaridevadmin')->group(function (): void {
    Route::get('/login', [AzariAdminLoginController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('azari.admin.login');
    Route::post('/login', [AzariAdminLoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('azari.admin.login.store');
});

Route::get('/azaridevadmin', DashboardController::class)->middleware(['auth.session', 'azari.staff'])->name('azari.admin.dashboard');

Route::prefix('azaridevadmin')->middleware(['auth.session', 'azari.staff'])->group(function (): void {
    Route::post('/logout', [AzariAdminLoginController::class, 'destroy'])->name('azari.admin.logout');

    Route::get('/branding', [SiteSettingController::class, 'edit'])->name('azari.admin.settings.edit');
    Route::put('/branding', [SiteSettingController::class, 'update'])
        ->middleware('azari.staff:administrator')
        ->name('azari.admin.settings.update');

    Route::get('/content', [ContentBlockController::class, 'index'])->name('azari.admin.content.index');
    Route::put('/content/{contentBlock}', [ContentBlockController::class, 'update'])
        ->middleware('azari.staff:administrator')
        ->name('azari.admin.content.update');

    Route::resource('properties', AdminPropertyController::class)
        ->except(['show', 'destroy'])
        ->names('azari.admin.properties');

    Route::middleware('azari.admin')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('azari.admin.staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('azari.admin.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('azari.admin.staff.store');
        Route::patch('/staff/{user}/toggle', [StaffController::class, 'toggle'])->name('azari.admin.staff.toggle');
    });
});

Route::get('/dashboard', fn () => redirect()->route('user.dashboard'))
    ->middleware(['auth', 'auth.session', 'verified', 'azari.customer'])
    ->name('dashboard');

Route::middleware(['auth', 'auth.session', 'azari.customer'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

require __DIR__.'/azari-sprints-3-4.php';

require __DIR__.'/azari-sprints-05-06.php';
require __DIR__.'/azari-booking-sprints-05-06.php';

require __DIR__.'/azari-public-completion.php';
require __DIR__.'/azari-sprints-07-08.php';

require __DIR__.'/azari-sprints-09-12.php';


require __DIR__.'/azari-sprints-13-16.php';

require __DIR__.'/azari-property-owner-extension.php';
