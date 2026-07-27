<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'whatsapp_notifications')) {
                $table->boolean('whatsapp_notifications')->default(false)->after('sms_notifications');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'whatsapp_notifications')) $table->dropColumn('whatsapp_notifications');
        });
    }
};
