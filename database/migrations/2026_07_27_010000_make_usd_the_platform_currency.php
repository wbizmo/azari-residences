<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['properties', 'bookings', 'payments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency')) {
                DB::table($table)->update(['currency' => 'USD']);
            }
        }

        if (Schema::hasTable('payment_verification_attempts') && Schema::hasColumn('payment_verification_attempts', 'reported_currency')) {
            DB::table('payment_verification_attempts')
                ->whereNotNull('reported_currency')
                ->update(['reported_currency' => 'USD']);
        }
    }

    public function down(): void
    {
        // Currency conversion is intentionally irreversible: amounts are not
        // exchange-rate converted by this migration.
    }
};
