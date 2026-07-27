<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotions') && ! Schema::hasColumn('promotions', 'show_on_homepage')) {
            Schema::table('promotions', function (Blueprint $table): void {
                $table->boolean('show_on_homepage')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('promotions') && Schema::hasColumn('promotions', 'show_on_homepage')) {
            Schema::table('promotions', function (Blueprint $table): void {
                $table->dropColumn('show_on_homepage');
            });
        }
    }
};
