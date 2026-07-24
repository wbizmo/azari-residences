<?php

use App\Http\Controllers\Admin\ContentBlockController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\AzariAdminLoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\AvailabilitySearchController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\PropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
// Availability routes are registered in azari-public-completion.php
Route::get('/residences/{property}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/favicon.svg', function () {
    return response(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#12211b"/><path d="M32 9l6.6 13.4 14.8 2.1-10.7 10.4 2.5 14.7L32 42.7 18.8 49.6l2.5-14.7L10.6 24.5l14.8-2.1L32 9z" fill="#bb8a3e"/></svg>',
        200,
        ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']
    );
})->name('public.favicon');

Route::middleware('guest')->prefix('azaridevadmin')->group(function (): void {
    Route::get('/login', [AzariAdminLoginController::class, 'create'])->name('azari.admin.login');
    Route::post('/login', [AzariAdminLoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('azari.admin.login.store');
});

Route::get('/azaridevadmin', function () {
    if (! auth()->check()) {
        return redirect()->route('azari.admin.login');
    }
    $user = auth()->user();
    abort_unless(
        $user->is_active
        && ((bool) $user->is_admin || in_array($user->staff_role, ['administrator', 'support'], true)),
        403
    );

    return app(DashboardController::class)();
})->name('azari.admin.dashboard');

Route::prefix('azaridevadmin')->middleware(['azari.staff'])->group(function (): void {
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

    Route::middleware('azari.staff:administrator')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('azari.admin.staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('azari.admin.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('azari.admin.staff.store');
        Route::patch('/staff/{user}/toggle', [StaffController::class, 'toggle'])->name('azari.admin.staff.toggle');
    });
});

Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

require __DIR__.'/azari-sprints-3-4.php';



require __DIR__.'/azari-sprints-05-06.php';
require __DIR__.'/azari-booking-sprints-05-06.php';


require __DIR__.'/azari-public-completion.php';