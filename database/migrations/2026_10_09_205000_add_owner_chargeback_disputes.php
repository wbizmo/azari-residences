<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 30);
            $table->string('provider_dispute_reference', 190);
            $table->string('status', 24)->default('open');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3);
            $table->string('evidence_reference', 190);
            $table->text('review_note')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_dispute_reference'], 'disputes_provider_reference_unique');
            $table->index(['owner_user_id', 'currency', 'status'], 'disputes_owner_open_idx');
            $table->index(['payment_id', 'status'], 'disputes_payment_status_idx');
        });

        Schema::table('owner_ledger_entries', function (Blueprint $table): void {
            $table->foreignId('payment_dispute_id')->nullable()->unique()
                ->constrained('payment_disputes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('owner_ledger_entries', function (Blueprint $table): void {
            $table->dropForeign(['payment_dispute_id']);
            $table->dropUnique(['payment_dispute_id']);
            $table->dropColumn('payment_dispute_id');
        });
        Schema::dropIfExists('payment_disputes');
    }
};
