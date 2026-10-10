<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->timestamp('media_reviewed_at')->nullable();
            $table->foreignId('media_reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('media_review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_reviewed_by');
            $table->dropColumn(['media_reviewed_at', 'media_review_note']);
        });
    }
};
