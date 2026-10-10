<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('property_photo_moderations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->char('path_hash', 64);
            $table->string('path', 1000);
            $table->string('status', 16)->default('pending');
            $table->string('alt_text', 300)->nullable();
            $table->string('attribution', 300)->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'path_hash']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('property_photo_moderations');
    }
};
