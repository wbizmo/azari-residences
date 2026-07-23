<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location');
            $table->string('country')->default('Nigeria');
            $table->string('property_type');
            $table->unsignedTinyInteger('bedrooms')->default(1);
            $table->unsignedTinyInteger('bathrooms')->default(1);
            $table->unsignedTinyInteger('max_guests')->default(2);
            $table->decimal('nightly_rate', 14, 2)->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon')->default('check_circle');
            $table->timestamps();
        });

        Schema::create('amenity_property', function (Blueprint $table): void {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['property_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_property');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('properties');
    }
};
