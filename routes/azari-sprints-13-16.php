<?php
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\CustomerContentController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\UserArea\ReviewController;
use App\Http\Controllers\UserArea\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'azari.customer'])->prefix('account')->name('user.')->group(function (): void {
    Route::get('/support',[SupportTicketController::class,'index'])->name('support.index');
    Route::post('/support',[SupportTicketController::class,'store'])->middleware('throttle:10,1')->name('support.store');
    Route::get('/support/{ticket}',[SupportTicketController::class,'show'])->name('support.show');
    Route::get('/support/{ticket}/attachments/{message}',[SupportTicketController::class,'attachment'])->middleware('signed')->name('support.attachment');
    Route::post('/support/{ticket}/reply',[SupportTicketController::class,'reply'])->middleware('throttle:20,1')->name('support.reply');
    Route::patch('/support/{ticket}/close',[SupportTicketController::class,'close'])->name('support.close');
    Route::patch('/support/{ticket}/reopen',[SupportTicketController::class,'reopen'])->name('support.reopen');
    Route::post('/bookings/{booking}/review',[ReviewController::class,'store'])->middleware('throttle:5,1')->name('reviews.store');
});
Route::prefix('azaridevadmin')->name('azari.admin.')->middleware(['auth.session', 'azari.staff'])->group(function (): void {
    Route::middleware('azari.permission:support-tickets.view')->group(function (): void {
        Route::get('/support',[AdminSupportTicketController::class,'index'])->name('support.index');
        Route::get('/support/{ticket}',[AdminSupportTicketController::class,'show'])->name('support.show');
        Route::get('/support/{ticket}/attachments/{message}',[AdminSupportTicketController::class,'attachment'])->middleware('signed')->name('support.attachment');
    });
    Route::put('/support/{ticket}',[AdminSupportTicketController::class,'update'])->middleware('azari.permission:support-tickets.edit')->name('support.update');
    Route::post('/support/{ticket}/reply',[AdminSupportTicketController::class,'reply'])->middleware('azari.permission:support-tickets.edit')->name('support.reply');
    Route::get('/reviews',[AdminReviewController::class,'index'])->middleware('azari.permission:reviews.view')->name('reviews.index');
    Route::put('/reviews/{review}',[AdminReviewController::class,'update'])->middleware('azari.permission:reviews.edit')->name('reviews.update');
    Route::get('/cms/customer-communications',[CustomerContentController::class,'edit'])->middleware('azari.permission:cms.view')->name('cms.customer-content.edit');
    Route::put('/cms/customer-communications',[CustomerContentController::class,'update'])->middleware('azari.permission:cms.edit')->name('cms.customer-content.update');
    Route::get('/promotions',[PromotionController::class,'index'])->middleware('azari.permission:promotions.view')->name('promotions.index');
    Route::post('/promotions',[PromotionController::class,'store'])->middleware('azari.permission:promotions.create')->name('promotions.store');
    Route::put('/promotions/{promotion}',[PromotionController::class,'update'])->middleware('azari.permission:promotions.edit')->name('promotions.update');
    Route::get('/vouchers',[VoucherController::class,'index'])->middleware('azari.permission:vouchers.view')->name('vouchers.index');
    Route::post('/vouchers',[VoucherController::class,'store'])->middleware('azari.permission:vouchers.create')->name('vouchers.store');
    Route::put('/vouchers/{voucher}',[VoucherController::class,'update'])->middleware('azari.permission:vouchers.edit')->name('vouchers.update');
    Route::get('/reports',[ReportController::class,'index'])->middleware('azari.permission:reports.view')->name('reports.index');
    Route::get('/reports/export/{format}',[ReportController::class,'export'])->middleware('azari.permission:reports.export')->name('reports.export');
    Route::get('/reports/print',[ReportController::class,'print'])->middleware('azari.permission:reports.view')->name('reports.print');
    Route::get('/audit-logs',[OperationsController::class,'audit'])->middleware('azari.permission:audit-logs.view')->name('audit-logs.index');
    Route::get('/system-health',[OperationsController::class,'index'])->middleware('azari.permission:system-health.view')->name('system-health.index');
    Route::get('/communications',[CommunicationController::class,'index'])->middleware('azari.permission:system-health.view')->name('communications.index');
    Route::post('/communications/{communicationLog}/retry',[CommunicationController::class,'retry'])->middleware('azari.permission:system-health.manage')->name('communications.retry');
    Route::post('/system-health/failed-jobs/{id}/retry',[OperationsController::class,'retryFailedJob'])
        ->whereNumber('id')->middleware('azari.permission:system-health.manage')
        ->middleware('throttle:5,1')->name('system-health.failed-jobs.retry');
    Route::post('/system-health/backups',[OperationsController::class,'backup'])->middleware('azari.permission:system-health.manage')->name('system-health.backup');
    Route::post('/system-health/backups/{backupRun}/verify',[OperationsController::class,'verifyBackup'])->middleware('azari.permission:system-health.manage')->name('system-health.backup.verify');
});
