<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('owner_reply_status', 20)->default('pending');
        });

        // Existing replies have already been visible on public pages.
        // Preserve their publication state through this migration.
        DB::table('reviews')->whereNotNull('owner_reply')
            ->where('owner_reply', '<>', '')
            ->update(['owner_reply_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('owner_reply_status');
        });
    }
};
