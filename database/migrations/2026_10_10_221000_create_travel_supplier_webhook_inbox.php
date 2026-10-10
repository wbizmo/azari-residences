<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_supplier_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_supplier_id')->constrained()->restrictOnDelete();
            $table->uuid('travel_request_id')->nullable();
            $table->foreign('travel_request_id')->references('id')->on('travel_requests')->nullOnDelete();
            $table->string('external_event_id', 128);
            $table->char('body_sha256', 64);
            $table->longText('encrypted_body')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('error_code', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['travel_supplier_id', 'external_event_id'], 'travel_webhook_supplier_event_unique');
            $table->index(['status', 'created_at'], 'travel_webhook_queue_idx');
            $table->index(['travel_request_id', 'created_at'], 'travel_webhook_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_supplier_webhook_events');
    }
};
