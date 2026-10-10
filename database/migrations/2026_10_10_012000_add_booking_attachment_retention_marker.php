<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->timestamp('attachment_purged_at')->nullable()->after('attachment_scan_error')->index();
        });
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->dropIndex(['attachment_purged_at']);
            $table->dropColumn('attachment_purged_at');
        });
    }
};
