<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_messages', 'client_token_hash')) {
                $table->char('client_token_hash', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('booking_messages', 'notification_queued_at')) {
                $table->timestamp('notification_queued_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            foreach (['client_token_hash', 'notification_queued_at'] as $column) {
                if (Schema::hasColumn('booking_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
