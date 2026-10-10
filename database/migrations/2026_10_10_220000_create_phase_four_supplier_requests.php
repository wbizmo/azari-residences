<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 24)->index();
            $table->string('name', 160);
            $table->string('status', 24)->default('pending');
            $table->string('integration_key', 100)->nullable();
            $table->string('support_email', 180)->nullable();
            $table->string('terms_url', 500)->nullable();
            $table->json('approved_regions')->nullable();
            $table->longText('compliance_evidence')->nullable(); // encrypted:array requires ciphertext text
            $table->timestamp('contract_verified_at')->nullable();
            $table->timestamp('safety_verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'status', 'contract_verified_at'], 'travel_supplier_discovery_idx');
        });

        Schema::create('travel_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_supplier_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('title', 180);
            $table->string('origin', 160)->nullable();
            $table->string('destination', 160)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('max_party')->default(1);
            $table->string('price_basis', 24)->default('per_person');
            $table->char('currency', 3);
            $table->unsignedBigInteger('base_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->unsignedBigInteger('deposit_minor')->default(0);
            $table->json('terms');
            $table->json('eligibility')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'published_at', 'expires_at'], 'travel_offer_discovery_idx');
            $table->index(['travel_supplier_id', 'kind'], 'travel_supplier_offers_idx');
        });

        Schema::create('travel_experience_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_offer_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->unsignedInteger('capacity');
            $table->timestamps();
            $table->unique(['travel_offer_id', 'starts_at'], 'travel_slot_start_unique');
        });

        Schema::create('travel_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_itinerary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('travel_supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('travel_offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('travel_experience_slot_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('status', 32)->default('requested');
            $table->string('idempotency_key', 100);
            $table->char('payload_hash', 64);
            $table->unsignedSmallInteger('party_size');
            $table->unsignedBigInteger('quoted_total_minor');
            $table->char('currency', 3);
            $table->json('quote_snapshot');
            $table->longText('preferences')->nullable(); // encrypted:array requires ciphertext text
            $table->boolean('data_share_consent')->default(false);
            $table->string('supplier_reference', 160)->nullable();
            $table->timestamp('supplier_acknowledged_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'travel_request_idempotency_unique');
            $table->unique(['travel_supplier_id', 'supplier_reference'], 'travel_supplier_ref_unique');
            $table->index(['travel_experience_slot_id', 'status', 'expires_at'], 'travel_slot_hold_idx');
            $table->index(['user_id', 'created_at'], 'travel_guest_requests_idx');
            $table->index(['trip_itinerary_id', 'created_at'], 'travel_itinerary_requests_idx');
            $table->index(['travel_supplier_id', 'status'], 'travel_supplier_request_status_idx');
        });

        Schema::create('travel_request_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('travel_request_id');
            $table->foreign('travel_request_id')->references('id')->on('travel_requests')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->json('details')->nullable();
            $table->timestamp('created_at');
            $table->index(['travel_request_id', 'created_at'], 'travel_events_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_request_events');
        Schema::dropIfExists('travel_requests');
        Schema::dropIfExists('travel_experience_slots');
        Schema::dropIfExists('travel_offers');
        Schema::dropIfExists('travel_suppliers');
    }
};
