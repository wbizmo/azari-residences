<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties') && ! Schema::hasColumn('properties', 'ownership_type')) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->string('ownership_type', 32)
                    ->default('azari')
                    ->after('owner_id')
                    ->index();
            });
        }

        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'ownership_type')) {
            DB::table('properties')
                ->whereNull('owner_id')
                ->update(['ownership_type' => 'azari']);

            DB::table('properties')
                ->whereNotNull('owner_id')
                ->update(['ownership_type' => 'third_party']);
        }

        if (Schema::hasTable('owner_payout_profiles')) {
            Schema::table('owner_payout_profiles', function (Blueprint $table): void {
                if (! Schema::hasColumn('owner_payout_profiles', 'verified_by')) {
                    $table->foreignId('verified_by')->nullable()->after('is_verified')
                        ->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('owner_payout_profiles', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }

                if (! Schema::hasColumn('owner_payout_profiles', 'verification_note')) {
                    $table->text('verification_note')->nullable()->after('verified_at');
                }
            });
        }

        if (Schema::hasTable('withdrawal_requests')) {
            Schema::table('withdrawal_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('withdrawal_requests', 'reconciled_by')) {
                    $table->foreignId('reconciled_by')->nullable()
                        ->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('withdrawal_requests', 'reconciled_at')) {
                    $table->timestamp('reconciled_at')->nullable();
                }

                if (! Schema::hasColumn('withdrawal_requests', 'reconciliation_note')) {
                    $table->text('reconciliation_note')->nullable();
                }

                if (! Schema::hasColumn('withdrawal_requests', 'retry_count')) {
                    $table->unsignedInteger('retry_count')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('withdrawal_requests')) {
            Schema::table('withdrawal_requests', function (Blueprint $table): void {
                foreach (['reconciled_by', 'reconciled_at', 'reconciliation_note', 'retry_count'] as $column) {
                    if (Schema::hasColumn('withdrawal_requests', $column)) {
                        if ($column === 'reconciled_by') {
                            try {
                                $table->dropConstrainedForeignId($column);
                            } catch (Throwable) {
                                $table->dropColumn($column);
                            }
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('owner_payout_profiles')) {
            Schema::table('owner_payout_profiles', function (Blueprint $table): void {
                foreach (['verified_by', 'verified_at', 'verification_note'] as $column) {
                    if (Schema::hasColumn('owner_payout_profiles', $column)) {
                        if ($column === 'verified_by') {
                            try {
                                $table->dropConstrainedForeignId($column);
                            } catch (Throwable) {
                                $table->dropColumn($column);
                            }
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'ownership_type')) {
            Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('ownership_type'));
        }
    }
};
