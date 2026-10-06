<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table): void {
                foreach ([
                    'accessibility_notes',
                    'children_policy',
                    'pet_policy',
                    'smoking_policy',
                    'party_policy',
                    'check_in_instructions',
                    'check_out_instructions',
                    'host_description',
                ] as $column) {
                    if (! Schema::hasColumn('properties', $column)) {
                        $table->text($column)->nullable();
                    }
                }
                if (! Schema::hasColumn('properties', 'host_name')) {
                    $table->string('host_name')->nullable();
                }
                if (! Schema::hasColumn('properties', 'house_rules')) {
                    $table->json('house_rules')->nullable();
                }
                if (! Schema::hasColumn('properties', 'faqs')) {
                    $table->json('faqs')->nullable();
                }
            });
        }

        if (! Schema::hasTable('property_points_of_interest')) {
            Schema::create('property_points_of_interest', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('category')->nullable();
                $table->decimal('distance_km', 8, 2)->nullable();
                $table->unsignedSmallInteger('walking_minutes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['property_id', 'sort_order'], 'property_poi_sort_idx');
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'payment_kind')) {
                    $table->string('payment_kind')->default('full')->after('status')->index();
                }
                if (! Schema::hasColumn('payments', 'refunded_amount')) {
                    $table->decimal('refunded_amount', 14, 2)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('payments', 'due_on')) {
                    $table->date('due_on')->nullable()->after('refunded_amount')->index();
                }
                if (! Schema::hasIndex('payments', 'payments_booking_status_paid_idx')) {
                    $table->index(['booking_id', 'status', 'paid_at'], 'payments_booking_status_paid_idx');
                }
            });
        }

        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table): void {
                $table->id();
                $table->string('reference')->unique();
                $table->string('idempotency_key', 64)->nullable()->unique();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                $table->string('status')->default('requested');
                $table->string('provider')->nullable();
                $table->string('provider_reference')->nullable();
                $table->string('reason')->nullable();
                $table->text('safe_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['booking_id', 'status'], 'refunds_booking_status_idx');
                $table->index(['payment_id', 'status'], 'refunds_payment_status_idx');
            });
        }

        if (! Schema::hasTable('user_favourites')) {
            Schema::create('user_favourites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'property_id'], 'user_favourites_unique');
                $table->index(['property_id', 'created_at'], 'user_favourites_property_idx');
            });
        }

        if (! Schema::hasTable('saved_searches')) {
            Schema::create('saved_searches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('fingerprint', 64);
                $table->json('parameters');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'fingerprint'], 'saved_searches_user_fingerprint_unique');
                $table->index(['user_id', 'last_used_at'], 'saved_searches_recent_idx');
            });
        }

        if (! Schema::hasTable('recently_viewed_properties')) {
            Schema::create('recently_viewed_properties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->timestamp('viewed_at');
                $table->timestamps();

                $table->unique(['user_id', 'property_id'], 'recent_property_user_unique');
                $table->index(['user_id', 'viewed_at'], 'recent_property_user_time_idx');
            });
        }

        if (! Schema::hasTable('booking_modification_requests')) {
            Schema::create('booking_modification_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type');
                $table->string('status')->default('pending');
                $table->json('requested_changes');
                $table->text('guest_note')->nullable();
                $table->text('staff_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['booking_id', 'status'], 'booking_modification_status_idx');
                $table->index(['user_id', 'created_at'], 'booking_modification_user_idx');
            });
        }

        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table): void {
                if (! Schema::hasColumn('reviews', 'property_id')) {
                    $table->foreignId('property_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('reviews', 'verified_stay')) {
                    $table->boolean('verified_stay')->default(false)->after('user_id')->index();
                }
                foreach (['cleanliness', 'comfort', 'facilities', 'location_score', 'staff_service', 'value_score', 'wifi_score'] as $column) {
                    if (! Schema::hasColumn('reviews', $column)) {
                        $table->unsignedTinyInteger($column)->nullable();
                    }
                }
                if (! Schema::hasColumn('reviews', 'positive_feedback')) {
                    $table->text('positive_feedback')->nullable();
                }
                if (! Schema::hasColumn('reviews', 'negative_feedback')) {
                    $table->text('negative_feedback')->nullable();
                }
                if (! Schema::hasColumn('reviews', 'trip_type')) {
                    $table->string('trip_type')->nullable();
                }
                if (! Schema::hasColumn('reviews', 'management_reply_by')) {
                    $table->foreignId('management_reply_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('reviews', 'management_replied_at')) {
                    $table->timestamp('management_replied_at')->nullable();
                }
                if (! Schema::hasColumn('reviews', 'moderation_reason')) {
                    $table->text('moderation_reason')->nullable();
                }
                if (! Schema::hasColumn('reviews', 'hidden_at')) {
                    $table->timestamp('hidden_at')->nullable();
                }
                if (! Schema::hasColumn('reviews', 'restored_at')) {
                    $table->timestamp('restored_at')->nullable();
                }
                if (! Schema::hasIndex('reviews', 'reviews_property_verified_status_idx')) {
                    $table->index(['property_id', 'verified_stay', 'status'], 'reviews_property_verified_status_idx');
                }
            });

            if (Schema::hasTable('bookings')) {
                DB::table('reviews')
                    ->whereNull('property_id')
                    ->orderBy('id')
                    ->chunkById(200, function ($reviews): void {
                        foreach ($reviews as $review) {
                            $booking = DB::table('bookings')->where('id', $review->booking_id)->first(['property_id', 'status', 'checked_out_at', 'completed_at']);
                            if (! $booking) {
                                continue;
                            }

                            DB::table('reviews')->where('id', $review->id)->update([
                                'property_id' => $booking->property_id,
                                'verified_stay' => in_array((string) $booking->status, ['checked_out', 'completed'], true)
                                    || $booking->checked_out_at !== null
                                    || $booking->completed_at !== null,
                            ]);
                        }
                    }, 'id');
            }
        }

        if (! Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table): void {
                $table->id();
                $table->string('idempotency_key', 64)->unique();
                $table->string('event');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('accommodation_type_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
                $table->string('anonymous_id_hash', 64)->nullable();
                $table->string('session_hash', 64)->nullable();
                $table->string('source')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['event', 'occurred_at'], 'analytics_event_time_idx');
                $table->index(['property_id', 'occurred_at'], 'analytics_property_time_idx');
                $table->index(['booking_id', 'event'], 'analytics_booking_event_idx');
            });
        }

        if (! Schema::hasTable('communication_preferences')) {
            Schema::create('communication_preferences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('email_transactional')->default(true);
                $table->boolean('sms_transactional')->default(true);
                $table->boolean('whatsapp_transactional')->default(true);
                $table->boolean('in_app_transactional')->default(true);
                $table->boolean('email_marketing')->default(false);
                $table->boolean('sms_marketing')->default(false);
                $table->boolean('whatsapp_marketing')->default(false);
                $table->string('locale', 16)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('communication_logs')) {
            Schema::table('communication_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('communication_logs', 'idempotency_key')) {
                    $table->string('idempotency_key', 64)->nullable()->unique();
                }
                if (! Schema::hasColumn('communication_logs', 'classification')) {
                    $table->string('classification')->default('transactional')->index();
                }
                if (! Schema::hasColumn('communication_logs', 'locale')) {
                    $table->string('locale', 16)->nullable();
                }
                if (! Schema::hasColumn('communication_logs', 'timezone')) {
                    $table->string('timezone', 64)->nullable();
                }
                if (! Schema::hasColumn('communication_logs', 'payload_hash')) {
                    $table->string('payload_hash', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('communication_logs', 'next_attempt_at')) {
                    $table->timestamp('next_attempt_at')->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('booking_operational_notes')) {
            Schema::create('booking_operational_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('visibility')->default('staff');
                $table->text('body');
                $table->timestamps();

                $table->index(['booking_id', 'created_at'], 'booking_ops_notes_idx');
            });
        }

        if (! Schema::hasTable('inventory_change_logs')) {
            Schema::create('inventory_change_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->foreignId('accommodation_type_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('from_date');
                $table->date('to_date');
                $table->string('source')->default('admin');
                $table->json('changes');
                $table->timestamps();

                $table->index(['property_id', 'created_at'], 'inventory_change_property_idx');
                $table->index(['accommodation_type_id', 'from_date', 'to_date'], 'inventory_change_type_dates_idx');
            });
        }

        if (Schema::hasTable('property_listings')) {
            Schema::table('property_listings', function (Blueprint $table): void {
                if (! Schema::hasColumn('property_listings', 'completeness_score')) {
                    $table->unsignedTinyInteger('completeness_score')->default(0);
                }
                if (! Schema::hasColumn('property_listings', 'completion_snapshot')) {
                    $table->json('completion_snapshot')->nullable();
                }
                if (! Schema::hasColumn('property_listings', 'publication_blockers')) {
                    $table->json('publication_blockers')->nullable();
                }
                if (! Schema::hasColumn('property_listings', 'last_completed_at')) {
                    $table->timestamp('last_completed_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasIndex('bookings', 'bookings_inventory_window_idx')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index(['accommodation_type_id', 'check_in', 'check_out', 'status'], 'bookings_inventory_window_idx');
                $table->index(['property_id', 'check_in', 'check_out'], 'bookings_property_stay_idx');
            });
        }

        if (Schema::hasTable('booking_holds') && ! Schema::hasIndex('booking_holds', 'holds_inventory_window_idx')) {
            Schema::table('booking_holds', function (Blueprint $table): void {
                $table->index(['accommodation_type_id', 'check_in', 'check_out', 'expires_at'], 'holds_inventory_window_idx');
            });
        }

        if (Schema::hasTable('accommodation_types') && ! Schema::hasIndex('accommodation_types', 'acc_types_search_rate_idx')) {
            Schema::table('accommodation_types', function (Blueprint $table): void {
                $table->index(['is_active', 'is_published', 'base_rate'], 'acc_types_search_rate_idx');
                $table->index(['bedrooms', 'bathrooms', 'max_guests'], 'acc_types_capacity_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_points_of_interest');

        if (Schema::hasTable('properties')) {
            foreach ([
                'faqs', 'house_rules', 'host_name', 'host_description',
                'check_out_instructions', 'check_in_instructions', 'party_policy',
                'smoking_policy', 'pet_policy', 'children_policy', 'accessibility_notes',
            ] as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    Schema::table('properties', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('accommodation_types')) {
            foreach (['acc_types_search_rate_idx', 'acc_types_capacity_idx'] as $index) {
                if (Schema::hasIndex('accommodation_types', $index)) {
                    Schema::table('accommodation_types', fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
        }

        if (Schema::hasTable('booking_holds') && Schema::hasIndex('booking_holds', 'holds_inventory_window_idx')) {
            Schema::table('booking_holds', fn (Blueprint $table) => $table->dropIndex('holds_inventory_window_idx'));
        }

        if (Schema::hasTable('bookings')) {
            foreach (['bookings_inventory_window_idx', 'bookings_property_stay_idx'] as $index) {
                if (Schema::hasIndex('bookings', $index)) {
                    Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
        }

        if (Schema::hasTable('property_listings')) {
            foreach (['last_completed_at', 'publication_blockers', 'completion_snapshot', 'completeness_score'] as $column) {
                if (Schema::hasColumn('property_listings', $column)) {
                    Schema::table('property_listings', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        Schema::dropIfExists('inventory_change_logs');
        Schema::dropIfExists('booking_operational_notes');

        if (Schema::hasTable('communication_logs')) {
            foreach (['next_attempt_at', 'payload_hash', 'timezone', 'locale', 'classification', 'idempotency_key'] as $column) {
                if (Schema::hasColumn('communication_logs', $column)) {
                    Schema::table('communication_logs', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        Schema::dropIfExists('communication_preferences');
        Schema::dropIfExists('analytics_events');

        if (Schema::hasTable('reviews')) {
            foreach (['management_reply_by', 'property_id'] as $column) {
                if (Schema::hasColumn('reviews', $column)) {
                    Schema::table('reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId($column));
                }
            }
            foreach ([
                'restored_at', 'hidden_at', 'moderation_reason', 'management_replied_at', 'trip_type',
                'negative_feedback', 'positive_feedback', 'wifi_score', 'value_score', 'staff_service',
                'location_score', 'facilities', 'comfort', 'cleanliness', 'verified_stay',
            ] as $column) {
                if (Schema::hasColumn('reviews', $column)) {
                    Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        Schema::dropIfExists('booking_modification_requests');
        Schema::dropIfExists('recently_viewed_properties');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('user_favourites');
        Schema::dropIfExists('refunds');

        if (Schema::hasTable('payments')) {
            foreach (['due_on', 'refunded_amount', 'payment_kind'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    Schema::table('payments', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
            if (Schema::hasIndex('payments', 'payments_booking_status_paid_idx')) {
                Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_booking_status_paid_idx'));
            }
        }
    }
};
