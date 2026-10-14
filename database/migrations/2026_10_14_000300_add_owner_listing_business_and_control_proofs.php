<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->string('operator_type', 24)->nullable()->after('user_id');
            $table->string('business_legal_name', 180)->nullable();
            $table->string('business_registration_number', 100)->nullable();
            $table->string('business_email', 255)->nullable();
            $table->string('business_phone', 40)->nullable();
            $table->string('business_address', 500)->nullable();
        });

        Schema::create('property_listing_control_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_listing_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['property_listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_listing_control_documents');

        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropColumn([
                'operator_type', 'business_legal_name', 'business_registration_number',
                'business_email', 'business_phone', 'business_address',
            ]);
        });
    }
};
