<?php

use App\Http\Controllers\Admin\IdentityManagementController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\PublicSite\PaymentCheckoutController;
use App\Http\Controllers\UserArea\AdditionalGuestController;
use App\Http\Controllers\UserArea\PhoneVerificationController;
use App\Http\Controllers\UserArea\UserBookingController;
use App\Http\Controllers\UserArea\UserContactController;
use App\Http\Controllers\UserArea\UserDashboardController;
use App\Http\Controllers\UserArea\UserDocumentController;
use App\Http\Controllers\UserArea\UserIdentityController;
use App\Http\Controllers\UserArea\UserNotificationController;
use App\Http\Controllers\UserArea\UserPaymentController;
use App\Http\Controllers\UserArea\UserProfileController;
use App\Http\Controllers\UserArea\UserSecurityController;
use App\Http\Controllers\Webhooks\TwilioMessageStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer', 'azari.owns-route'])->prefix('account')->name('user.')->group(function (): void {
    Route::get('/', UserDashboardController::class)->name('dashboard');
    Route::get('/bookings', [UserBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{reference}', [UserBookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{reference}/receipt', [UserBookingController::class, 'receipt'])->name('bookings.receipt');
    Route::get('/payments', [UserPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [UserPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/retry', [UserPaymentController::class, 'retry'])->name('payments.retry');
    Route::get('/documents', [UserDocumentController::class, 'index'])->name('documents.index');
    Route::get('/identity', [UserIdentityController::class, 'index'])->name('identity.index');
    Route::post('/identity', [UserIdentityController::class, 'store'])->name('identity.store');
    Route::get('/identity/{document}/download', [UserIdentityController::class, 'download'])->name('identity.download');
    Route::get('/additional-guests', [AdditionalGuestController::class, 'index'])->name('guests.index');
    Route::post('/bookings/{reference}/guests/{guest}/identity', [AdditionalGuestController::class, 'store'])->name('guests.identity.store');
    Route::get('/bookings/{reference}/guests/{guest}/identity/{document}/download', [AdditionalGuestController::class, 'download'])->name('guests.identity.download');
    Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [UserNotificationController::class, 'read'])->name('notifications.read');
    Route::patch('/notifications/read-all', [UserNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/profile', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::patch('/preferences', [UserProfileController::class, 'preferences'])->name('preferences.update');
    Route::post('/phone-verification/send', [PhoneVerificationController::class, 'send'])->name('phone.send');
    Route::post('/phone-verification/verify', [PhoneVerificationController::class, 'verify'])->name('phone.verify');
    Route::get('/security', [UserSecurityController::class, 'index'])->name('security.index');
    Route::delete('/security/sessions/{session}', [UserSecurityController::class, 'destroySession'])->name('security.sessions.destroy');
    Route::get('/contact', fn (UserContactController $controller) => $controller('contact'))->name('contact');
    Route::get('/service-requests', fn (UserContactController $controller) => $controller('service-requests'))->name('service-requests');
    Route::get('/support-tickets', fn (UserContactController $controller) => $controller('support-tickets'))->name('support-tickets');
});

Route::post('/webhooks/twilio/message-status', TwilioMessageStatusController::class)->middleware('throttle:600,1')->name('webhooks.twilio.message-status');

Route::get('/booking/{reference}/payment', [PaymentCheckoutController::class, 'select'])->name('public.payment.select');
Route::post('/booking/{reference}/payment', [PaymentCheckoutController::class, 'initialise'])->middleware('throttle:20,1')->name('public.payment.initialise');
Route::get('/booking/{reference}/payment-receipt/{payment}', [PaymentCheckoutController::class, 'receipt'])->name('public.payment.receipt');
Route::match(['GET', 'POST'], '/payments/{provider}/callback/{payment}', [PaymentCheckoutController::class, 'callback'])->middleware('throttle:120,1')->name('payments.callback');
Route::match(['GET', 'POST'], '/payments/{provider}/webhook', [PaymentCheckoutController::class, 'webhook'])->middleware('throttle:240,1')->name('payments.webhook');

Route::prefix('azari-admin')->name('azari.admin.')->middleware(['auth.session', 'azari.staff'])->group(function (): void {
    Route::get('/settings/integrations', [SystemSettingsController::class, 'edit'])->name('settings.integrations');
    Route::put('/settings/integrations', [SystemSettingsController::class, 'update'])
        ->middleware('azari.admin')
        ->name('settings.integrations.update');

    Route::get('/payments/providers', [AdminPaymentController::class, 'providers'])->name('payments.providers');
    Route::post('/payments/providers/{provider}/test', [AdminPaymentController::class, 'testProvider'])
        ->middleware('azari.admin')
        ->name('payments.providers.test');
    Route::get('/payments/create', [AdminPaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [AdminPaymentController::class, 'store'])
        ->middleware('azari.permission:payments.manage')
        ->name('payments.store');
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/reconcile', [AdminPaymentController::class, 'reconcile'])
        ->middleware('azari.permission:payments.manage')
        ->name('payments.reconcile');
    Route::get('/payments/{payment}/proof', [AdminPaymentController::class, 'proof'])->name('payments.proof');

    Route::get('/identities', [IdentityManagementController::class, 'index'])->name('identities.index');
    Route::get('/identities/types', [IdentityManagementController::class, 'types'])->name('identities.types');
    Route::post('/identities/types', [IdentityManagementController::class, 'storeType'])
        ->middleware('azari.admin')
        ->name('identities.types.store');
    Route::put('/identities/types/{identityType}', [IdentityManagementController::class, 'updateType'])
        ->middleware('azari.admin')
        ->name('identities.types.update');
    Route::get('/identities/missing', [IdentityManagementController::class, 'missing'])->name('identities.missing');
    Route::get('/identities/audit', [IdentityManagementController::class, 'audit'])->name('identities.audit');
    Route::patch('/identities/users/{document}/review', [IdentityManagementController::class, 'reviewUser'])
        ->middleware('azari.permission:identities.manage')
        ->name('identities.users.review');
    Route::patch('/identities/guests/{document}/review', [IdentityManagementController::class, 'reviewGuest'])
        ->middleware('azari.permission:identities.manage')
        ->name('identities.guests.review');
    Route::get('/identities/users/{document}/download', [IdentityManagementController::class, 'downloadUser'])->name('identities.users.download');
    Route::get('/identities/guests/{document}/download', [IdentityManagementController::class, 'downloadGuest'])->name('identities.guests.download');
});

Route::prefix('azaridevadmin')->name('azari.admin.')->middleware(['azari.admin'])->group(function (): void {
    Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    Route::put('/staff/{user}/password', [StaffController::class, 'replacePassword'])->name('staff.password');
    Route::put('/staff/{user}/suspend', [StaffController::class, 'suspend'])->name('staff.suspend');
    Route::put('/staff/{user}/reactivate', [StaffController::class, 'reactivate'])->name('staff.reactivate');
    Route::get('/staff/{user}/activity', [StaffController::class, 'activity'])->name('staff.activity');
});
