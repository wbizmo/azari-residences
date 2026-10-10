<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table): void {
            $table->text('webhook_secret')->nullable();
        });
        Schema::create('channel_webhook_inbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_event_id', 128);
            $table->char('payload_sha256', 64);
            $table->longText('encrypted_payload');
            $table->string('status', 24)->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('event_occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['channel_connection_id','external_event_id'], 'channel_inbox_event_unique');
            $table->index(['status','created_at'], 'channel_inbox_queue_idx');
        });
        Schema::create('channel_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('idempotency_key', 128);
            $table->longText('encrypted_payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['channel_connection_id','idempotency_key'], 'channel_outbox_key_unique');
            $table->index(['status','next_attempt_at'], 'channel_outbox_queue_idx');
        });
        Schema::create('fx_reference_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('units_per_base', 16, 8);
            $table->string('source', 100);
            $table->string('source_reference', 190);
            $table->timestamp('observed_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['source', 'source_reference'], 'fx_source_reference_unique');
            $table->index(['base_currency','quote_currency','expires_at'], 'fx_valid_pair_idx');
        });
        Schema::create('fx_quote_locks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fx_reference_rate_id')->constrained()->restrictOnDelete();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->unsignedBigInteger('base_minor');
            $table->unsignedBigInteger('quote_minor');
            $table->decimal('locked_rate', 16, 8);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','expires_at'], 'fx_quote_owner_expiry_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('fx_quote_locks');
        Schema::dropIfExists('fx_reference_rates');
        Schema::dropIfExists('channel_outbox');
        Schema::dropIfExists('channel_webhook_inbox');
        Schema::table('channel_connections', fn (Blueprint $table) => $table->dropColumn('webhook_secret'));
    }
};
