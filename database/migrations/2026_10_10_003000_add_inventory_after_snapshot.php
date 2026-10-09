<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_change_logs', function (Blueprint $table): void {
            $table->json('after_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_change_logs', function (Blueprint $table): void {
            $table->dropColumn('after_snapshot');
        });
    }
};
