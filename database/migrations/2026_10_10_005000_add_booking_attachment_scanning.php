<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->string('attachment_scan_status', 16)->nullable()->index();
            $table->timestamp('attachment_scanned_at')->nullable();
            $table->timestamp('attachment_scan_claimed_at')->nullable();
            $table->timestamp('attachment_scan_next_attempt_at')->nullable();
            $table->unsignedSmallInteger('attachment_scan_attempts')->default(0);
            $table->string('attachment_scan_error', 200)->nullable();
        });

        // Existing private attachments must not retain implied approval.
        DB::table('booking_messages')->whereNotNull('attachment_path')
            ->update(['attachment_scan_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->dropIndex(['attachment_scan_status']);
            $table->dropColumn([
                'attachment_scan_status', 'attachment_scanned_at',
                'attachment_scan_claimed_at', 'attachment_scan_next_attempt_at',
                'attachment_scan_attempts', 'attachment_scan_error',
            ]);
        });
    }
};
