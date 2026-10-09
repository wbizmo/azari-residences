<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_holds') && ! Schema::hasColumn('booking_holds', 'pricing_snapshot')) {
            Schema::table('booking_holds', function (Blueprint $table): void {
                $table->json('pricing_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_holds') && Schema::hasColumn('booking_holds', 'pricing_snapshot')) {
            Schema::table('booking_holds', fn (Blueprint $table) => $table->dropColumn('pricing_snapshot'));
        }
    }
};
