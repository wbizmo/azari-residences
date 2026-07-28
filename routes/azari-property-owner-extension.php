<?php

use App\Http\Controllers\Admin\OwnerMarketplaceController;
use App\Http\Controllers\User\PropertyOwnerController;
use Illuminate\Support\Facades\Route;

Route::get('/list-your-property', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->isStaff() ? 'azari.admin.dashboard' : 'user.owner.dashboard')
        : redirect()->route('login')->with('status', 'Sign in or create an account to list your property.');
})->name('public.list-property');

Route::prefix('user/property-centre')
    ->name('user.owner.')
    ->middleware(['auth', 'auth.session', 'verified', 'azari.customer'])
    ->group(function (): void {
        Route::get('/', [PropertyOwnerController::class, 'dashboard'])->name('dashboard');
        Route::get('/agreement', [PropertyOwnerController::class, 'agreement'])->name('agreement');
        Route::post('/agreement', [PropertyOwnerController::class, 'signAgreement'])->name('agreement.sign');
        Route::get('/agreement/download', [PropertyOwnerController::class, 'agreementDownload'])->name('agreement.download');

        Route::get('/listings', [PropertyOwnerController::class, 'index'])->name('listings.index');
        Route::get('/listings/create', [PropertyOwnerController::class, 'create'])->name('listings.create');
        Route::post('/listings', [PropertyOwnerController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing}', [PropertyOwnerController::class, 'show'])->name('listings.show');
        Route::get('/listings/{listing}/edit', [PropertyOwnerController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [PropertyOwnerController::class, 'update'])->name('listings.update');

        Route::get('/earnings', [PropertyOwnerController::class, 'earnings'])->name('earnings');
        Route::get('/withdrawals', [PropertyOwnerController::class, 'withdrawals'])->name('withdrawals');
        Route::put('/withdrawal-profile', [PropertyOwnerController::class, 'updatePayoutProfile'])->name('payout-profile.update');
        Route::post('/withdrawals', [PropertyOwnerController::class, 'requestWithdrawal'])->name('withdrawals.store');
    });

Route::prefix('azaridevadmin/property-owners')
    ->name('azari.admin.')
    ->middleware(['auth.session', 'azari.staff'])
    ->group(function (): void {
        Route::get('/listings', [OwnerMarketplaceController::class, 'listings'])
            ->middleware('azari.staff:property-owners.view')->name('owner-listings.index');
        Route::get('/listings/{listing}', [OwnerMarketplaceController::class, 'showListing'])
            ->middleware('azari.staff:property-owners.view')->name('owner-listings.show');
        Route::post('/listings/{listing}/review', [OwnerMarketplaceController::class, 'markUnderReview'])
            ->middleware('azari.staff:property-owners.review')->name('owner-listings.review');
        Route::post('/listings/{listing}/approve', [OwnerMarketplaceController::class, 'approveListing'])
            ->middleware('azari.staff:property-owners.review')->name('owner-listings.approve');
        Route::post('/listings/{listing}/decline', [OwnerMarketplaceController::class, 'declineListing'])
            ->middleware('azari.staff:property-owners.review')->name('owner-listings.decline');
        Route::get('/listings/{listing}/agreement', [OwnerMarketplaceController::class, 'agreementDownload'])
            ->middleware('azari.staff:property-owners.view')->name('owner-listings.agreement');

        Route::get('/withdrawals', [OwnerMarketplaceController::class, 'withdrawals'])
            ->middleware('azari.staff:owner-withdrawals.view')->name('owner-withdrawals.index');
        Route::get('/withdrawals/{withdrawal}', [OwnerMarketplaceController::class, 'showWithdrawal'])
            ->middleware('azari.staff:owner-withdrawals.view')->name('owner-withdrawals.show');
        Route::post('/withdrawals/{withdrawal}/process', [OwnerMarketplaceController::class, 'processWithdrawal'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-withdrawals.process');
        Route::post('/withdrawals/{withdrawal}/retry', [OwnerMarketplaceController::class, 'retryWithdrawal'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-withdrawals.retry');
        Route::post('/withdrawals/{withdrawal}/reconcile-paid', [OwnerMarketplaceController::class, 'reconcileWithdrawalPaid'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-withdrawals.reconcile-paid');
        Route::post('/withdrawals/{withdrawal}/reconcile-not-paid', [OwnerMarketplaceController::class, 'reconcileWithdrawalNotPaid'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-withdrawals.reconcile-not-paid');
        Route::post('/withdrawals/{withdrawal}/reject', [OwnerMarketplaceController::class, 'rejectWithdrawal'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-withdrawals.reject');

        Route::post('/payout-profiles/{profile}/verify', [OwnerMarketplaceController::class, 'verifyPayoutProfile'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-payout-profiles.verify');
        Route::post('/payout-profiles/{profile}/unverify', [OwnerMarketplaceController::class, 'unverifyPayoutProfile'])
            ->middleware('azari.staff:owner-withdrawals.process')->name('owner-payout-profiles.unverify');

        Route::get('/settings', [OwnerMarketplaceController::class, 'settings'])
            ->middleware('azari.staff:owner-settings.manage')->name('owner-settings.edit');
        Route::put('/settings', [OwnerMarketplaceController::class, 'updateSettings'])
            ->middleware('azari.staff:owner-settings.manage')->name('owner-settings.update');
    });
