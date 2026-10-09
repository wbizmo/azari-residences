<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->uuid('client_token')->nullable();
            $table->unique(['conversation_id', 'sender_id', 'client_token'], 'booking_messages_sender_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->dropUnique('booking_messages_sender_token_unique');
            $table->dropColumn('client_token');
        });
    }
};
