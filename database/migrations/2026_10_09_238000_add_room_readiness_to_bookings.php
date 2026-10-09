<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'room_ready_at')) {
                $table->timestamp('room_ready_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'room_ready_by')) {
                $table->foreignId('room_ready_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'room_ready_by')) {
                $table->dropConstrainedForeignId('room_ready_by');
            }
            if (Schema::hasColumn('bookings', 'room_ready_at')) {
                $table->dropColumn('room_ready_at');
            }
        });
    }
};
