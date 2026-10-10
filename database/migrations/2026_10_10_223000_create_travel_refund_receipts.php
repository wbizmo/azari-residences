<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_refund_receipts', function (Blueprint $table): void {
            $table->string('provider_reference', 160)->primary();
            $table->foreignId('travel_fulfillment_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index('travel_fulfillment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_refund_receipts');
    }
};
