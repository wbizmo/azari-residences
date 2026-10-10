<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dining_partners', function (Blueprint $t) {
            $t->id();
            $t->string('name', 160);
            $t->string('status', 24)->default('pending_review');
            $t->string('address', 350);
            $t->string('city', 100);
            $t->string('timezone', 64);
            $t->string('support_email', 180)->nullable();
            $t->string('website', 500)->nullable();
            $t->json('dietary_options')->nullable();
            $t->json('accessibility')->nullable();
            $t->json('hours')->nullable();
            $t->text('disclosures');
            $t->timestamp('details_verified_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'city', 'details_verified_at'], 'dining_discovery_idx');
        });
        Schema::create('dining_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('dining_partner_id')->constrained()->restrictOnDelete();
            $t->foreignId('trip_itinerary_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 32)->default('pending_concierge');
            $t->string('idempotency_key', 100);
            $t->char('payload_hash', 64);
            $t->unsignedTinyInteger('party_size');
            $t->timestamp('requested_for');
            $t->longText('private_preferences')->nullable();
            $t->boolean('supplier_share_consent')->default(false);
            $t->string('provider_reference', 160)->nullable();
            $t->timestamp('provider_confirmed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id','idempotency_key'], 'dining_user_idempotency');
            $t->unique(['dining_partner_id','provider_reference'], 'dining_provider_unique');
            $t->index(['trip_itinerary_id','requested_for'], 'dining_itinerary_idx');
            $t->index(['status','requested_for'], 'dining_admin_idx');
        });
        Schema::create('trip_assemblies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('trip_itinerary_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('idempotency_key', 100);
            $t->char('payload_hash', 64);
            $t->string('status', 32)->default('review_required');
            $t->json('item_snapshot');
            $t->json('currency_totals');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id','idempotency_key'], 'trip_assembly_idempotency');
            $t->index(['status','updated_at'], 'trip_assembly_recovery_idx');
        });
        Schema::create('trip_assembly_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('trip_assembly_id');
            $t->foreign('trip_assembly_id')->references('id')->on('trip_assemblies')->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 48);
            $t->string('previous_status', 32)->nullable();
            $t->string('new_status', 32);
            $t->json('details')->nullable();
            $t->timestamp('created_at');
            $t->index(['trip_assembly_id','id'], 'trip_assembly_timeline');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('trip_assembly_events');
        Schema::dropIfExists('trip_assemblies');
        Schema::dropIfExists('dining_requests');
        Schema::dropIfExists('dining_partners');
    }
};
