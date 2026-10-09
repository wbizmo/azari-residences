<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('owner_ledger_entries') && ! Schema::hasColumn('owner_ledger_entries', 'refund_id')) {
            Schema::table('owner_ledger_entries', function (Blueprint $table): void {
                $table->foreignId('refund_id')->nullable()->unique()->constrained('refunds')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('owner_ledger_entries') && Schema::hasColumn('owner_ledger_entries', 'refund_id')) {
            Schema::table('owner_ledger_entries', function (Blueprint $table): void {
                $table->dropForeign(['refund_id']);
                $table->dropUnique(['refund_id']);
                $table->dropColumn('refund_id');
            });
        }
    }
};
