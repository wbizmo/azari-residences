<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Do not silently delete or overwrite historical verified reviews.
        // Resolve duplicate booking rows explicitly before enforcing integrity.
        $duplicates = DB::table('reviews')
            ->whereNotNull('booking_id')
            ->select('booking_id')
            ->groupBy('booking_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicates) {
            throw new \RuntimeException(
                'Duplicate reviews for a booking exist; reconcile and audit them before this migration.'
            );
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique('booking_id', 'reviews_booking_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique('reviews_booking_unique');
        });
    }
};
