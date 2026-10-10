<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_itineraries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('trip_itinerary_id')
                ->nullable()->constrained('trip_itineraries')->nullOnDelete();
            $table->index(['user_id', 'trip_itinerary_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'trip_itinerary_id']);
            $table->dropConstrainedForeignId('trip_itinerary_id');
        });
        Schema::dropIfExists('trip_itineraries');
    }
};
