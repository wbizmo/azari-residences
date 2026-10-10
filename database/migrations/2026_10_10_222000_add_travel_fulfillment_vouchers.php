<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('travel_request_id')->unique();
            $table->foreign('travel_request_id')->references('id')->on('travel_requests')->restrictOnDelete();
            $table->foreignId('travel_supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('awaiting_verification');
            $table->string('provider_confirmation', 160)->nullable();
            $table->string('verified_payment_reference', 160)->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('provider_confirmed_at')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['travel_supplier_id', 'provider_confirmation'], 'travel_fulfillment_provider_unique');
            $table->unique(['verified_payment_reference', 'currency'], 'travel_fulfillment_payment_unique');
            $table->index(['status', 'created_at'], 'travel_fulfillment_status_idx');
        });

        Schema::create('travel_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_fulfillment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->longText('token_encrypted');
            $table->string('status', 24)->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'issued_at'], 'travel_voucher_status_idx');
        });

        Schema::create('travel_financial_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_fulfillment_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 128);
            $table->string('type', 32);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('provider_reference', 160);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->unique(['travel_fulfillment_id', 'event_key'], 'travel_financial_idempotency');
            $table->index(['type', 'created_at'], 'travel_financial_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_financial_events');
        Schema::dropIfExists('travel_vouchers');
        Schema::dropIfExists('travel_fulfillments');
    }
};
