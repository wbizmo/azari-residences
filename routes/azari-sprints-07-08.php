<?php

use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\PaymentDisputeController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Location\AddressLookupController;
use App\Http\Controllers\PublicSite\PaymentCheckoutController;
use App\Http\Controllers\UserArea\AdditionalGuestController;
use App\Http\Controllers\UserArea\BookingSelfServiceController;
use App\Http\Controllers\UserArea\UserDiscoveryController;
use App\Http\Controllers\UserArea\PhoneVerificationController;
use App\Http\Controllers\UserArea\UserBookingController;
use App\Http\Controllers\UserArea\UserContactController;
use App\Http\Controllers\UserArea\UserDashboardController;
use App\Http\Controllers\UserArea\DojahVerificationController;
use App\Http\Controllers\PublicSite\PublicGuestVerificationController;
use App\Http\Controllers\Webhooks\DojahWebhookController;
use App\Http\Controllers\UserArea\UserDocumentController;
use App\Http\Controllers\UserArea\UserNotificationController;
use App\Http\Controllers\UserArea\UserPaymentController;
use App\Http\Controllers\UserArea\UserProfileController;
use App\Http\Controllers\UserArea\UserSecurityController;
use App\Http\Controllers\Webhooks\TwilioMessageStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer', 'azari.owns-route'])
    ->prefix('account')
    ->name('user.')
    ->group(function (): void {
        Route::get('/', UserDashboardController::class)->name('dashboard');

        Route::post('/favourites/{property}', [UserDiscoveryController::class, 'toggleFavourite'])
            ->name('favourites.toggle');
        Route::post('/saved-searches', [UserDiscoveryController::class, 'storeSearch'])
            ->name('saved-searches.store');
        Route::delete('/saved-searches/{savedSearch}', [UserDiscoveryController::class, 'destroySearch'])
            ->name('saved-searches.destroy');

        Route::get('/bookings', [UserBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{reference}', [UserBookingController::class, 'show'])->name('bookings.show');
        Route::get('/bookings/{reference}/receipt', [UserBookingController::class, 'receipt'])->name('bookings.receipt');
        Route::post('/bookings/{reference}/cancel', [BookingSelfServiceController::class, 'cancelBooking'])
            ->middleware('throttle:5,10')->name('bookings.cancel');
        Route::post('/bookings/{reference}/modifications', [BookingSelfServiceController::class, 'storeModification'])
            ->middleware('throttle:20,1')
            ->name('bookings.modifications.store');
        Route::post('/bookings/{reference}/modifications/{modification}/pay', [BookingSelfServiceController::class, 'payDateAmendment'])
            ->middleware('throttle:5,10')->name('bookings.modifications.pay');
        Route::post('/bookings/{reference}/modifications/{modification}/accept', [BookingSelfServiceController::class, 'acceptDateAmendment'])
            ->middleware('throttle:10,1')->name('bookings.modifications.accept');
        // The Dojah identity portal is intentionally reachable before identity
        // verification; do not put the verification guard on these endpoints.
        Route::get('/identity', [DojahVerificationController::class, 'user'])
            ->name('identity.index');
        Route::get('/identity/status', [DojahVerificationController::class, 'status'])
            ->middleware('throttle:60,1')->name('identity.status');

        Route::get('/payments', [UserPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [UserPaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/retry', [UserPaymentController::class, 'retry'])->name('payments.retry');
        Route::get('/documents', [UserDocumentController::class, 'index'])->name('documents.index');

        Route::get('/additional-guests', [AdditionalGuestController::class, 'index'])
            ->name('guests.index');
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

// Invite links contain no PII; the controller requires an email-code session
// grant before revealing guest details, granting account access or status.
Route::prefix('guest-verification')->name('guest-verification.')->group(function (): void {
    Route::get('/{reference}/{position}', [PublicGuestVerificationController::class, 'show'])
        ->whereNumber('position')->middleware('throttle:40,1')->name('show');
    Route::post('/{reference}/{position}/code/send', [PublicGuestVerificationController::class, 'sendCode'])
        ->whereNumber('position')->middleware('throttle:3,10')->name('code.send');
    Route::post('/{reference}/{position}/code/verify', [PublicGuestVerificationController::class, 'verifyCode'])
        ->whereNumber('position')->middleware('throttle:10,10')->name('code.verify');
    Route::get('/{reference}/{position}/status', [PublicGuestVerificationController::class, 'status'])
        ->whereNumber('position')->middleware('throttle:60,1')->name('status');
    Route::post('/{reference}/{position}/account', [PublicGuestVerificationController::class, 'createAccount'])
        ->whereNumber('position')->middleware('throttle:5,10')->name('account.create');
    Route::post('/{reference}/{position}/link', [PublicGuestVerificationController::class, 'linkAccount'])
        ->whereNumber('position')->middleware('throttle:10,10')->name('account.link');
});

Route::post('/webhooks/dojah', DojahWebhookController::class)
    ->middleware('throttle:120,1')->name('webhooks.dojah');

Route::middleware(['auth', 'auth.session', 'throttle:60,1'])
    ->prefix('location')
    ->name('location.address.')
    ->group(function (): void {
        Route::get('/search', [AddressLookupController::class, 'search'])->name('search');
        Route::get('/reverse', [AddressLookupController::class, 'reverse'])->name('reverse');
    });


Route::post('/webhooks/twilio/message-status', TwilioMessageStatusController::class)
    ->middleware('throttle:600,1')
    ->name('webhooks.twilio.message-status');

Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer'])
    ->group(function (): void {
        Route::get('/booking/{reference}/payment', [PaymentCheckoutController::class, 'select'])->name('public.payment.select');
        Route::post('/booking/{reference}/payment', [PaymentCheckoutController::class, 'initialise'])
            ->middleware('throttle:20,1')
            ->name('public.payment.initialise');
        Route::get('/booking/{reference}/payment/flutterwave/{payment}/instructions', [PaymentCheckoutController::class, 'flutterwaveInstructions'])
            ->name('public.payment.flutterwave.instructions');
        Route::get('/booking/{reference}/payment-receipt/{payment}', [PaymentCheckoutController::class, 'receipt'])
            ->name('public.payment.receipt');
    });

Route::match(['GET', 'POST'], '/payments/{provider}/callback/{payment}', [PaymentCheckoutController::class, 'callback'])
    ->middleware('throttle:120,1')
    ->name('payments.callback');

Route::match(['GET', 'POST'], '/payments/{provider}/webhook', [PaymentCheckoutController::class, 'webhook'])
    ->middleware('throttle:240,1')
    ->name('payments.webhook');

Route::prefix('azaridevadmin')
    ->name('azari.admin.')
    ->middleware(['auth.session', 'azari.staff'])
    ->group(function (): void {
        Route::get('/settings/integrations', [SystemSettingsController::class, 'edit'])->name('settings.integrations');
        Route::put('/settings/integrations', [SystemSettingsController::class, 'update'])
            ->middleware('azari.admin')
            ->middleware('azari.step-up')->name('settings.integrations.update');

        Route::get('/payments/providers', [AdminPaymentController::class, 'providers'])->name('payments.providers');
        Route::post('/payments/providers/{provider}/test', [AdminPaymentController::class, 'testProvider'])
            ->middleware('azari.admin')
            ->middleware('azari.step-up')->name('payments.providers.test');
        Route::get('/payments/create', [AdminPaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [AdminPaymentController::class, 'store'])
            ->middleware('azari.permission:payments.manage')
            ->name('payments.store');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/reconcile', [AdminPaymentController::class, 'reconcile'])
            ->middleware('azari.permission:payments.manage')
            ->middleware('azari.step-up')->name('payments.reconcile');
        Route::post('/payments/{payment}/refunds', [RefundController::class, 'store'])
            ->middleware('azari.permission:payments.manage')
            ->middleware('azari.step-up')->name('payments.refunds.store');
        Route::patch('/payments/{payment}/refunds/{refund}', [RefundController::class, 'update'])
            ->middleware('azari.permission:payments.manage')
            ->middleware('azari.step-up')->name('payments.refunds.update');
        Route::post('/payments/{payment}/refunds/{refund}/dispatch', [RefundController::class, 'dispatchProvider'])
            ->middleware('azari.permission:payments.manage')->middleware('azari.step-up')->name('payments.refunds.dispatch');
        Route::post('/payments/{payment}/refunds/{refund}/reconcile', [RefundController::class, 'reconcileProvider'])
            ->middleware('azari.permission:payments.manage')->middleware('azari.step-up')->name('payments.refunds.reconcile');
        Route::post('/payments/{payment}/disputes', [PaymentDisputeController::class, 'store'])
            ->middleware('azari.permission:payments.manage')->middleware('azari.step-up')->name('payments.disputes.store');
        Route::post('/payment-disputes/{dispute}/resolve', [PaymentDisputeController::class, 'resolve'])
            ->middleware('azari.permission:payments.manage')->middleware('azari.step-up')->name('payments.disputes.resolve');
        Route::get('/payments/{payment}/proof', [AdminPaymentController::class, 'proof'])->name('payments.proof');
    });

Route::prefix('azaridevadmin')
    ->name('azari.admin.')
    ->middleware(['azari.admin'])
    ->group(function (): void {
        Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
        Route::put('/staff/{user}/password', [StaffController::class, 'replacePassword'])->name('staff.password');
        Route::put('/staff/{user}/suspend', [StaffController::class, 'suspend'])->name('staff.suspend');
        Route::put('/staff/{user}/reactivate', [StaffController::class, 'reactivate'])->name('staff.reactivate');
        Route::get('/staff/{user}/activity', [StaffController::class, 'activity'])->name('staff.activity');
    });
