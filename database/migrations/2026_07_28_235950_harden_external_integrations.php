<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('communication_logs')) {
            Schema::table('communication_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('communication_logs', 'provider_status')) {
                    $table->string('provider_status', 64)->nullable()->after('provider_reference');
                }
                if (! Schema::hasColumn('communication_logs', 'status_updated_at')) {
                    $table->timestamp('status_updated_at')->nullable()->after('provider_status');
                }
                if (! Schema::hasColumn('communication_logs', 'provider_error_code')) {
                    $table->string('provider_error_code', 64)->nullable()->after('safe_error');
                }
            });
        }

        if (Schema::hasTable('payment_events')) {
            Schema::table('payment_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('payment_events', 'attempt_count')) {
                    $table->unsignedInteger('attempt_count')->default(0);
                }
                if (! Schema::hasColumn('payment_events', 'next_attempt_at')) {
                    $table->timestamp('next_attempt_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_events')) {
            Schema::table('payment_events', function (Blueprint $table): void {
                foreach (['attempt_count', 'next_attempt_at'] as $column) {
                    if (Schema::hasColumn('payment_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('communication_logs')) {
            Schema::table('communication_logs', function (Blueprint $table): void {
                foreach (['provider_status', 'status_updated_at', 'provider_error_code'] as $column) {
                    if (Schema::hasColumn('communication_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
