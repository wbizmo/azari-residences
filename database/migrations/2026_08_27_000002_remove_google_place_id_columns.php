<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('properties', 'google_place_id')) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->dropIndex(['google_place_id']);
                $table->dropColumn('google_place_id');
            });
        }

        if (Schema::hasColumn('bookings', 'property_google_place_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropColumn('property_google_place_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('properties', 'google_place_id')) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->string('google_place_id', 255)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('bookings', 'property_google_place_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->string('property_google_place_id', 255)->nullable();
            });
        }
    }
};
