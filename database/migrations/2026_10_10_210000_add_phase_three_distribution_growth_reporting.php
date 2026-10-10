<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('key_hash', 64)->unique();
            $table->json('scopes');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('partner_booking_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_client_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('payload_hash', 64);
            $table->json('filters');
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending_guest_checkout');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['partner_client_id','idempotency_key'], 'partner_intent_idempotent');
            $table->index(['partner_client_id','created_at']);
        });
        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('template_key', 100);
            $table->string('subject', 180);
            $table->string('status', 30)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('marketing_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->string('status', 30)->default('reserved');
            $table->timestamp('reserved_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamps();
            $table->unique(['marketing_campaign_id','user_id','event_key'], 'marketing_delivery_dedup');
            $table->index(['user_id','reserved_at']);
        });
        Schema::create('commission_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('commission_basis_points')->default(0);
            $table->char('currency', 3);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['property_id','version'], 'commission_property_version_unique');
            $table->index(['property_id','effective_from']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('personalization_opt_out')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('personalization_opt_out'));
        Schema::dropIfExists('commission_agreements');
        Schema::dropIfExists('marketing_deliveries');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('partner_booking_intents');
        Schema::dropIfExists('partner_clients');
    }
};
