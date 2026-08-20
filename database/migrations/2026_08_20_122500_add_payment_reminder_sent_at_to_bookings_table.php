<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'payment_reminder_sent_at')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->timestamp('payment_reminder_sent_at')
                    ->nullable()
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'payment_reminder_sent_at')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropColumn('payment_reminder_sent_at');
            });
        }
    }
};
