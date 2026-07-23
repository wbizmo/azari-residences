<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique();
            }
        });

        if (! Schema::hasTable('navigation_items')) {
            Schema::create('navigation_items', function (Blueprint $table): void {
                $table->id();
                $table->string('label');
                $table->string('url');
                $table->string('location')->default('header')->index();
                $table->string('target')->default('_self');
                $table->string('icon')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->nullOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('homepage_sections')) {
            Schema::create('homepage_sections', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('type')->default('content');
                $table->json('content')->nullable();
                $table->string('background_media')->nullable();
                $table->string('status')->default('published')->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table): void {
                $table->id();
                $table->string('disk')->default('public');
                $table->string('path')->unique();
                $table->string('original_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('size');
                $table->string('title')->nullable();
                $table->string('alt_text')->nullable();
                $table->text('caption')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_archived')->default(false)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('theme_revisions')) {
            Schema::create('theme_revisions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->json('settings');
                $table->string('status')->default('draft')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('country');
                $table->string('city');
                $table->text('address')->nullable();
                $table->string('timezone')->default('Africa/Lagos');
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('buildings')) {
            Schema::create('buildings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('location_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('floors')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('room_types')) {
            Schema::create('room_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('icon')->default('bed');
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table): void {
                $columns = Schema::getColumnListing('properties');

                if (! in_array('location_id', $columns, true)) {
                    $table->foreignId('location_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }
                if (! in_array('building_id', $columns, true)) {
                    $table->foreignId('building_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
                }
                if (! in_array('room_type_id', $columns, true)) {
                    $table->foreignId('room_type_id')->nullable()->after('building_id')->constrained()->nullOnDelete();
                }
                if (! in_array('code', $columns, true)) {
                    $table->string('code')->nullable()->unique();
                }
                if (! in_array('unit_number', $columns, true)) {
                    $table->string('unit_number')->nullable();
                }
                if (! in_array('floor', $columns, true)) {
                    $table->string('floor')->nullable();
                }
                if (! in_array('adult_capacity', $columns, true)) {
                    $table->unsignedTinyInteger('adult_capacity')->default(2);
                }
                if (! in_array('child_capacity', $columns, true)) {
                    $table->unsignedTinyInteger('child_capacity')->default(0);
                }
                if (! in_array('bed_configuration', $columns, true)) {
                    $table->string('bed_configuration')->nullable();
                }
                if (! in_array('room_size', $columns, true)) {
                    $table->decimal('room_size', 8, 2)->nullable();
                }
                if (! in_array('check_in_time', $columns, true)) {
                    $table->time('check_in_time')->nullable();
                }
                if (! in_array('check_out_time', $columns, true)) {
                    $table->time('check_out_time')->nullable();
                }
                if (! in_array('weekend_rate', $columns, true)) {
                    $table->decimal('weekend_rate', 14, 2)->nullable();
                }
                if (! in_array('cleaning_fee', $columns, true)) {
                    $table->decimal('cleaning_fee', 14, 2)->default(0);
                }
                if (! in_array('security_deposit', $columns, true)) {
                    $table->decimal('security_deposit', 14, 2)->default(0);
                }
                if (! in_array('service_charge', $columns, true)) {
                    $table->decimal('service_charge', 14, 2)->default(0);
                }
                if (! in_array('tax_rate', $columns, true)) {
                    $table->decimal('tax_rate', 6, 3)->default(0);
                }
                if (! in_array('video_url', $columns, true)) {
                    $table->string('video_url')->nullable();
                }
                if (! in_array('virtual_tour_url', $columns, true)) {
                    $table->string('virtual_tour_url')->nullable();
                }
                if (! in_array('status', $columns, true)) {
                    $table->string('status')->default('available')->index();
                }
                if (! in_array('internal_notes', $columns, true)) {
                    $table->text('internal_notes')->nullable();
                }
            });
        }

        if (! Schema::hasTable('property_images')) {
            Schema::create('property_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('path');
                $table->string('title')->nullable();
                $table->string('alt_text')->nullable();
                $table->text('caption')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_cover')->default(false)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pricing_rules')) {
            Schema::create('pricing_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('rule_type')->default('seasonal');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->json('days_of_week')->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->decimal('percentage', 8, 3)->nullable();
                $table->unsignedSmallInteger('minimum_stay')->nullable();
                $table->unsignedSmallInteger('maximum_stay')->nullable();
                $table->unsignedInteger('priority')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('amenities')) {
            Schema::table('amenities', function (Blueprint $table): void {
                $columns = Schema::getColumnListing('amenities');
                if (! in_array('description', $columns, true)) {
                    $table->text('description')->nullable();
                }
                if (! in_array('sort_order', $columns, true)) {
                    $table->unsignedInteger('sort_order')->default(0);
                }
                if (! in_array('is_active', $columns, true)) {
                    $table->boolean('is_active')->default(true)->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('property_images');

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table): void {
                foreach ([
                    'location_id', 'building_id', 'room_type_id', 'code',
                    'unit_number', 'floor', 'adult_capacity', 'child_capacity',
                    'bed_configuration', 'room_size', 'check_in_time',
                    'check_out_time', 'weekend_rate', 'cleaning_fee',
                    'security_deposit', 'service_charge', 'tax_rate',
                    'video_url', 'virtual_tour_url', 'status', 'internal_notes',
                ] as $column) {
                    if (Schema::hasColumn('properties', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('room_types');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('theme_revisions');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('navigation_items');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
        });
    }
};
