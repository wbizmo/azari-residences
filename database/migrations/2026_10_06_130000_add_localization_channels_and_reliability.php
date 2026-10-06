<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 16)->nullable()->after('timezone');
            }
            if (! Schema::hasColumn('users', 'display_currency')) {
                $table->char('display_currency', 3)->nullable()->after('locale');
            }
        });

        Schema::table('properties', function (Blueprint $table): void {
            if (! Schema::hasColumn('properties', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('currency');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'property_timezone')) {
                $table->string('property_timezone', 64)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('bookings', 'booking_locale')) {
                $table->string('booking_locale', 16)->nullable()->after('property_timezone');
            }
        });

        if (! Schema::hasTable('channel_connections')) {
            Schema::create('channel_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->foreignId('accommodation_type_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('provider')->default('ical');
                $table->string('name');
                $table->text('import_url')->nullable();
                $table->string('export_token', 64)->unique();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('fail_closed')->default(true);
                $table->unsignedInteger('stale_after_minutes')->default(180);
                $table->string('status')->default('never_synced')->index();
                $table->timestamp('last_attempted_at')->nullable();
                $table->timestamp('last_successful_sync_at')->nullable();
                $table->timestamp('next_retry_at')->nullable()->index();
                $table->unsignedInteger('consecutive_failures')->default(0);
                $table->text('last_safe_error')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->index(['property_id', 'is_active'], 'channel_property_active_idx');
            });
        }

        if (! Schema::hasTable('channel_reservations')) {
            Schema::create('channel_reservations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->foreignId('accommodation_type_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('external_id');
                $table->string('status')->default('active')->index();
                $table->date('starts_on')->index();
                $table->date('ends_on')->index();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('summary')->nullable();
                $table->string('source_hash', 64)->nullable();
                $table->timestamp('external_updated_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['channel_connection_id', 'external_id'], 'channel_external_unique');
                $table->index(['property_id', 'starts_on', 'ends_on', 'status'], 'channel_property_dates_idx');
                $table->index(['accommodation_type_id', 'starts_on', 'ends_on', 'status'], 'channel_type_dates_idx');
            });
        }

        if (! Schema::hasTable('channel_sync_runs')) {
            Schema::create('channel_sync_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('started')->index();
                $table->unsignedInteger('imported')->default(0);
                $table->unsignedInteger('updated')->default(0);
                $table->unsignedInteger('cancelled')->default(0);
                $table->text('safe_error')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['channel_connection_id', 'started_at'], 'channel_run_connection_idx');
            });
        }

        if (! Schema::hasTable('system_heartbeats')) {
            Schema::create('system_heartbeats', function (Blueprint $table): void {
                $table->id();
                $table->string('component')->unique();
                $table->timestamp('last_seen_at')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('channel_sync_runs');
        Schema::dropIfExists('channel_reservations');
        Schema::dropIfExists('channel_connections');

        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['booking_locale', 'property_timezone'] as $column) {
                if (Schema::hasColumn('bookings', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('properties', function (Blueprint $table): void {
            if (Schema::hasColumn('properties', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
        Schema::table('users', function (Blueprint $table): void {
            foreach (['display_currency', 'locale'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
