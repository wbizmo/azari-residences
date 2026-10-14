<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('property_publication_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->string('review_digest', 64)->nullable();
            $table->text('review_note');
            $table->json('checklist')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'id']);
        });
    }
    public function down(): void { Schema::dropIfExists('property_publication_reviews'); }
};