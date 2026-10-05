<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accommodation_types')) {
            Schema::create('accommodation_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->foreignId('room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->unsignedTinyInteger('bedrooms')->default(1);
                $table->unsignedTinyInteger('bathrooms')->default(1);
                $table->unsignedSmallInteger('adult_capacity')->default(2);
                $table->unsignedSmallInteger('child_capacity')->default(0);
                $table->unsignedSmallInteger('max_guests')->default(2);
                $table->string('bed_configuration')->nullable();
                $table->decimal('room_size', 8, 2)->nullable();
                $table->unsignedInteger('total_inventory')->default(1);
                $table->decimal('base_rate', 14, 2)->default(0);
                $table->decimal('weekend_rate', 14, 2)->nullable();
                $table->decimal('cleaning_fee', 14, 2)->default(0);
                $table->decimal('service_charge', 14, 2)->default(0);
                $table->decimal('security_deposit', 14, 2)->default(0);
                $table->decimal('tax_rate', 7, 4)->default(0);
                $table->char('currency', 3)->default('USD');
                $table->unsignedInteger('minimum_stay')->default(1);
                $table->unsignedInteger('maximum_stay')->nullable();
                $table->boolean('same_day_booking')->default(false);
                $table->string('cover_image')->nullable();
                $table->json('gallery')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_published')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['property_id', 'slug'], 'acc_types_property_slug_unique');
                $table->index(['property_id', 'is_active', 'is_published'], 'acc_types_property_public_idx');
            });
        }

        if (! Schema::hasTable('accommodation_type_amenity')) {
            Schema::create('accommodation_type_amenity', function (Blueprint $table): void {
                $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
                $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
                $table->primary(['accommodation_type_id', 'amenity_id'], 'acc_type_amenity_primary');
            });
        }

        if (! Schema::hasTable('inventory_dates')) {
            Schema::create('inventory_dates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('sellable_inventory')->nullable();
                $table->unsignedInteger('maintenance_inventory')->default(0);
                $table->boolean('stop_sell')->default(false);
                $table->boolean('closed_to_arrival')->default(false);
                $table->boolean('closed_to_departure')->default(false);
                $table->unsignedInteger('minimum_stay')->nullable();
                $table->unsignedInteger('maximum_stay')->nullable();
                $table->decimal('price_override', 14, 2)->nullable();
                $table->timestamps();

                $table->unique(['accommodation_type_id', 'date'], 'inventory_type_date_unique');
                $table->index(['date', 'accommodation_type_id'], 'inventory_date_type_idx');
            });
        }

        if (! Schema::hasTable('cancellation_policies')) {
            Schema::create('cancellation_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('policy_type')->default('property_default');
                $table->unsignedInteger('free_cancel_hours')->nullable();
                $table->decimal('fee_percentage', 7, 4)->default(0);
                $table->decimal('fee_amount', 14, 2)->default(0);
                $table->boolean('charge_first_night')->default(false);
                $table->string('no_show_policy')->default('same_as_cancellation');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_policies')) {
            Schema::create('payment_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('payment_type')->default('full_prepayment');
                $table->string('deposit_type')->nullable();
                $table->decimal('deposit_value', 14, 2)->nullable();
                $table->unsignedInteger('balance_due_days_before_arrival')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rate_plans')) {
            Schema::create('rate_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
                $table->foreignId('cancellation_policy_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payment_policy_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('code');
                $table->string('pricing_adjustment_type')->default('none');
                $table->decimal('pricing_adjustment', 14, 4)->default(0);
                $table->string('meal_plan')->nullable();
                $table->json('inclusions')->nullable();
                $table->unsignedInteger('minimum_stay')->nullable();
                $table->unsignedInteger('maximum_stay')->nullable();
                $table->unsignedInteger('minimum_advance_days')->nullable();
                $table->unsignedInteger('maximum_advance_days')->nullable();
                $table->boolean('is_refundable')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_public')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['accommodation_type_id', 'code'], 'rate_plan_type_code_unique');
                $table->index(['accommodation_type_id', 'is_active', 'is_public'], 'rate_plan_public_idx');
            });
        }

        if (! Schema::hasTable('daily_rates')) {
            Schema::create('daily_rates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
                $table->foreignId('rate_plan_id')->nullable()->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->decimal('amount', 14, 2);
                $table->unsignedInteger('minimum_stay')->nullable();
                $table->unsignedInteger('maximum_stay')->nullable();
                $table->boolean('stop_sell')->default(false);
                $table->timestamps();

                $table->unique(['accommodation_type_id', 'rate_plan_id', 'date'], 'daily_rate_type_plan_date_unique');
                $table->index(['date', 'accommodation_type_id'], 'daily_rate_date_type_idx');
            });
        }

        if (! Schema::hasTable('pricing_promotions')) {
            Schema::create('pricing_promotions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('accommodation_type_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('rate_plan_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('discount_type')->default('percentage');
                $table->decimal('discount_value', 14, 4);
                $table->decimal('maximum_discount', 14, 2)->nullable();
                $table->date('stay_starts_on')->nullable();
                $table->date('stay_ends_on')->nullable();
                $table->timestamp('book_starts_at')->nullable();
                $table->timestamp('book_ends_at')->nullable();
                $table->unsignedInteger('minimum_nights')->nullable();
                $table->unsignedInteger('maximum_nights')->nullable();
                $table->boolean('is_stackable')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('priority')->default(0);
                $table->timestamps();

                $table->index(['property_id', 'is_active'], 'pricing_promo_property_active_idx');
                $table->index(['accommodation_type_id', 'is_active'], 'pricing_promo_type_active_idx');
            });
        }

        if (Schema::hasTable('booking_holds')) {
            if (! Schema::hasColumn('booking_holds', 'accommodation_type_id')) {
                Schema::table('booking_holds', fn (Blueprint $table) =>
                    $table->foreignId('accommodation_type_id')->nullable()->after('property_id')->constrained()->nullOnDelete()
                );
            }
            if (! Schema::hasColumn('booking_holds', 'rate_plan_id')) {
                Schema::table('booking_holds', fn (Blueprint $table) =>
                    $table->foreignId('rate_plan_id')->nullable()->after('accommodation_type_id')->constrained()->nullOnDelete()
                );
            }
        }

        if (Schema::hasTable('bookings')) {
            if (! Schema::hasColumn('bookings', 'accommodation_type_id')) {
                Schema::table('bookings', fn (Blueprint $table) =>
                    $table->foreignId('accommodation_type_id')->nullable()->after('property_id')->constrained()->nullOnDelete()
                );
            }
            if (! Schema::hasColumn('bookings', 'rate_plan_id')) {
                Schema::table('bookings', fn (Blueprint $table) =>
                    $table->foreignId('rate_plan_id')->nullable()->after('accommodation_type_id')->constrained()->nullOnDelete()
                );
            }
            if (! Schema::hasColumn('bookings', 'accommodation_type_name_snapshot')) {
                Schema::table('bookings', fn (Blueprint $table) =>
                    $table->string('accommodation_type_name_snapshot')->nullable()->after('property_name_snapshot')
                );
            }
            if (! Schema::hasColumn('bookings', 'rate_plan_name_snapshot')) {
                Schema::table('bookings', fn (Blueprint $table) =>
                    $table->string('rate_plan_name_snapshot')->nullable()->after('accommodation_type_name_snapshot')
                );
            }
            if (! Schema::hasColumn('bookings', 'policy_snapshot')) {
                Schema::table('bookings', fn (Blueprint $table) =>
                    $table->json('policy_snapshot')->nullable()->after('pricing_snapshot')
                );
            }
        }

        if (Schema::hasTable('pricing_rules')) {
            if (! Schema::hasColumn('pricing_rules', 'accommodation_type_id')) {
                Schema::table('pricing_rules', fn (Blueprint $table) =>
                    $table->foreignId('accommodation_type_id')->nullable()->after('property_id')->constrained()->cascadeOnDelete()
                );
            }
            if (! Schema::hasColumn('pricing_rules', 'rate_plan_id')) {
                Schema::table('pricing_rules', fn (Blueprint $table) =>
                    $table->foreignId('rate_plan_id')->nullable()->after('accommodation_type_id')->constrained()->cascadeOnDelete()
                );
            }
            if (! Schema::hasColumn('pricing_rules', 'adjustment_type')) {
                Schema::table('pricing_rules', fn (Blueprint $table) =>
                    $table->string('adjustment_type')->default('auto')->after('rule_type')
                );
            }
        }

        $this->addSearchIndexes();
        $this->backfillLegacyInventory();
    }

    private function addSearchIndexes(): void
    {
        if (Schema::hasTable('locations')) {
            Schema::table('locations', function (Blueprint $table): void {
                $table->index('name', 'locations_name_search_idx');
                $table->index('city', 'locations_city_search_idx');
                $table->index('country', 'locations_country_search_idx');
            });
        }

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->index('name', 'properties_name_search_idx');
                if (Schema::hasColumn('properties', 'address_city')) {
                    $table->index('address_city', 'properties_city_search_idx');
                }
                $table->index('location', 'properties_location_search_idx');
            });
        }
    }

    private function backfillLegacyInventory(): void
    {
        if (! Schema::hasTable('properties') || ! Schema::hasTable('accommodation_types')) {
            return;
        }

        DB::table('properties')
            ->orderBy('id')
            ->chunkById(100, function ($properties): void {
                foreach ($properties as $property) {
                    $existing = DB::table('accommodation_types')
                        ->where('property_id', $property->id)
                        ->orderBy('id')
                        ->first();

                    if ($existing) {
                        $typeId = $existing->id;
                    } else {
                        $slug = Str::slug((string) ($property->property_type ?: $property->name ?: 'standard')) ?: 'standard';

                        $typeId = DB::table('accommodation_types')->insertGetId([
                            'property_id' => $property->id,
                            'room_type_id' => $property->room_type_id ?? null,
                            'name' => (string) ($property->property_type ?: 'Standard accommodation'),
                            'slug' => $slug,
                            'code' => 'RES-'.$property->id.'-STD',
                            'description' => $property->short_description ?? null,
                            'bedrooms' => max(0, (int) ($property->bedrooms ?? 1)),
                            'bathrooms' => max(0, (int) ($property->bathrooms ?? 1)),
                            'adult_capacity' => max(1, (int) ($property->adult_capacity ?? $property->max_guests ?? 2)),
                            'child_capacity' => max(0, (int) ($property->child_capacity ?? 0)),
                            'max_guests' => max(1, (int) ($property->max_guests ?? 2)),
                            'bed_configuration' => $property->bed_configuration ?? null,
                            'room_size' => $property->room_size ?? null,
                            'total_inventory' => 1,
                            'base_rate' => (float) ($property->nightly_rate ?? 0),
                            'weekend_rate' => $property->weekend_rate ?? null,
                            'cleaning_fee' => (float) ($property->cleaning_fee ?? 0),
                            'service_charge' => (float) ($property->service_charge ?? $property->service_fee ?? 0),
                            'security_deposit' => (float) ($property->security_deposit ?? 0),
                            'tax_rate' => (float) ($property->tax_rate ?? 0),
                            'currency' => strtoupper((string) ($property->currency ?? 'USD')),
                            'minimum_stay' => max(1, (int) ($property->minimum_stay ?? 1)),
                            'maximum_stay' => $property->maximum_stay ?? null,
                            'same_day_booking' => (bool) ($property->same_day_booking ?? false),
                            'cover_image' => $property->cover_image ?? null,
                            'gallery' => $property->gallery ?? null,
                            'is_active' => ! in_array((string) ($property->status ?? ''), ['inactive', 'archived'], true),
                            'is_published' => (bool) ($property->is_published ?? false),
                            'sort_order' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $ratePlanId = DB::table('rate_plans')
                        ->where('accommodation_type_id', $typeId)
                        ->orderBy('id')
                        ->value('id');

                    if (! $ratePlanId) {
                        $ratePlanId = DB::table('rate_plans')->insertGetId([
                            'accommodation_type_id' => $typeId,
                            'name' => 'Standard',
                            'code' => 'STANDARD',
                            'pricing_adjustment_type' => 'none',
                            'pricing_adjustment' => 0,
                            'is_refundable' => true,
                            'is_active' => true,
                            'is_public' => true,
                            'sort_order' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if (Schema::hasTable('booking_holds')) {
                        DB::table('booking_holds')
                            ->where('property_id', $property->id)
                            ->whereNull('accommodation_type_id')
                            ->update([
                                'accommodation_type_id' => $typeId,
                                'rate_plan_id' => $ratePlanId,
                            ]);
                    }

                    if (Schema::hasTable('bookings')) {
                        DB::table('bookings')
                            ->where('property_id', $property->id)
                            ->whereNull('accommodation_type_id')
                            ->update([
                                'accommodation_type_id' => $typeId,
                                'rate_plan_id' => $ratePlanId,
                                'accommodation_type_name_snapshot' => DB::raw("COALESCE(accommodation_type_name_snapshot, '".str_replace("'", "''", (string) ($property->property_type ?: 'Standard accommodation'))."')"),
                                'rate_plan_name_snapshot' => DB::raw("COALESCE(rate_plan_name_snapshot, 'Standard')"),
                            ]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        if (Schema::hasTable('pricing_rules')) {
            foreach (['rate_plan_id', 'accommodation_type_id', 'adjustment_type'] as $column) {
                if (Schema::hasColumn('pricing_rules', $column)) {
                    Schema::table('pricing_rules', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('bookings')) {
            foreach (['policy_snapshot', 'rate_plan_name_snapshot', 'accommodation_type_name_snapshot', 'rate_plan_id', 'accommodation_type_id'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('booking_holds')) {
            foreach (['rate_plan_id', 'accommodation_type_id'] as $column) {
                if (Schema::hasColumn('booking_holds', $column)) {
                    Schema::table('booking_holds', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        Schema::dropIfExists('pricing_promotions');
        Schema::dropIfExists('daily_rates');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('payment_policies');
        Schema::dropIfExists('cancellation_policies');
        Schema::dropIfExists('inventory_dates');
        Schema::dropIfExists('accommodation_type_amenity');
        Schema::dropIfExists('accommodation_types');
    }
};
