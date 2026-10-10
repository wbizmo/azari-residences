<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('channel_webhook_inbox', function (Blueprint $table): void {
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->string('safe_error', 255)->nullable();
        });
        Schema::table('channel_outbox', function (Blueprint $table): void {
            $table->string('safe_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('channel_outbox', fn (Blueprint $table) => $table->dropColumn('safe_error'));
        Schema::table('channel_webhook_inbox', fn (Blueprint $table) => $table->dropColumn(['next_attempt_at', 'safe_error']));
    }
};
