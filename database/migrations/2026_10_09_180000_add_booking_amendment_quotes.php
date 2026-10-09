<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_modification_requests', function (Blueprint $table): void {
            $table->json('price_quote')->nullable();
            $table->timestamp('quote_expires_at')->nullable()->index();
            $table->timestamp('accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_modification_requests', function (Blueprint $table): void {
            $table->dropIndex(['quote_expires_at']);
            $table->dropColumn(['price_quote', 'quote_expires_at', 'accepted_at']);
        });
    }
};
