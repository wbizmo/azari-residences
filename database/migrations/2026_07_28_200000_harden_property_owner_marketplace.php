<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (Schema::hasColumn('properties', 'owner_listing_id')) {
                $table->foreign('owner_listing_id')
                    ->references('id')
                    ->on('property_listings')
                    ->nullOnDelete();
            }
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('withdrawal_requests', 'idempotency_key')) {
                $table->string('idempotency_key', 80)->nullable()->unique()->after('reference');
            }
            if (! Schema::hasColumn('withdrawal_requests', 'provider_sent_at')) {
                $table->timestamp('provider_sent_at')->nullable()->after('processing_started_at');
            }
            if (! Schema::hasColumn('withdrawal_requests', 'reconciliation_required_at')) {
                $table->timestamp('reconciliation_required_at')->nullable()->after('provider_sent_at');
            }
            if (! Schema::hasColumn('withdrawal_requests', 'last_error')) {
                $table->text('last_error')->nullable()->after('provider_response');
            }

            $table->index(['status', 'requested_at'], 'owner_withdrawals_status_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->dropIndex('owner_withdrawals_status_requested_idx');

            foreach ([
                'idempotency_key',
                'provider_sent_at',
                'reconciliation_required_at',
                'last_error',
            ] as $column) {
                if (Schema::hasColumn('withdrawal_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('properties', function (Blueprint $table): void {
            if (Schema::hasColumn('properties', 'owner_listing_id')) {
                $table->dropForeign(['owner_listing_id']);
            }
        });
    }
};
