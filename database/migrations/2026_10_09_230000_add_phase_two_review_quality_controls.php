<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'locale')) {
                $table->string('locale', 16)->nullable()->index();
            }
        });

        if (! Schema::hasTable('review_helpful_votes')) {
            Schema::create('review_helpful_votes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['review_id', 'user_id']);
                $table->index(['review_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('review_reports')) {
            Schema::create('review_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('reason', 40);
                $table->text('details')->nullable();
                $table->string('status', 30)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['review_id', 'user_id']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_helpful_votes');

        Schema::table('reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('reviews', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};
