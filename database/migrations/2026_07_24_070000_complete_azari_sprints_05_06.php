<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('booking_holds')) {
            Schema::create('booking_holds', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_id')->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('token')->unique();
                $table->date('check_in')->index();
                $table->date('check_out')->index();
                $table->unsignedInteger('adults')->default(1);
                $table->unsignedInteger('children')->default(0);
                $table->unsignedInteger('rooms')->default(1);
                $table->timestamp('expires_at')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('maintenance_periods')) {
            Schema::create('maintenance_periods', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_id')->index();
                $table->date('starts_on')->index();
                $table->date('ends_on')->index();
                $table->string('title');
                $table->text('notes')->nullable();
                $table->boolean('blocks_booking')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('booking_add_ons')) {
            Schema::create('booking_add_ons', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('pricing_type')->default('flat');
                $table->decimal('price', 14, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('booking_add_on_booking')) {
            Schema::create('booking_add_on_booking', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('booking_add_on_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);
                $table->timestamps();
                $table->unique(['booking_id','booking_add_on_id']);
            });
        }

        if (!Schema::hasTable('booking_status_histories')) {
            Schema::create('booking_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('booking_status_histories', 'metadata')) {
            Schema::table('booking_status_histories', fn (Blueprint $table) => $table->json('metadata')->nullable()->after('note'));
        }

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table): void {
                if (!Schema::hasColumn('properties','minimum_stay')) $table->unsignedInteger('minimum_stay')->default(1);
                if (!Schema::hasColumn('properties','maximum_stay')) $table->unsignedInteger('maximum_stay')->nullable();
                if (!Schema::hasColumn('properties','same_day_booking')) $table->boolean('same_day_booking')->default(false);
                if (!Schema::hasColumn('properties','service_fee')) $table->decimal('service_fee',14,2)->default(0);
                if (!Schema::hasColumn('properties','tax_rate')) $table->decimal('tax_rate',7,4)->default(0);
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table): void {
                if (!Schema::hasColumn('bookings','hold_token')) $table->uuid('hold_token')->nullable()->index();
                if (!Schema::hasColumn('bookings','nightly_rate')) $table->decimal('nightly_rate',14,2)->default(0);
                if (!Schema::hasColumn('bookings','nights')) $table->unsignedInteger('nights')->default(1);
                if (!Schema::hasColumn('bookings','fee_total')) $table->decimal('fee_total',14,2)->default(0);
                if (!Schema::hasColumn('bookings','add_on_total')) $table->decimal('add_on_total',14,2)->default(0);
                if (!Schema::hasColumn('bookings','tax_rate')) $table->decimal('tax_rate',7,4)->default(0);
                if (!Schema::hasColumn('bookings','pricing_snapshot')) $table->json('pricing_snapshot')->nullable();
                if (!Schema::hasColumn('bookings','room_assignment_locked_at')) $table->timestamp('room_assignment_locked_at')->nullable();
                if (!Schema::hasColumn('bookings','payment_transfer_locked_at')) $table->timestamp('payment_transfer_locked_at')->nullable();
                if (!Schema::hasColumn('bookings','modified_at')) $table->timestamp('modified_at')->nullable();
                if (!Schema::hasColumn('bookings','expires_at')) $table->timestamp('expires_at')->nullable()->index();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('booking_add_on_booking');
        Schema::dropIfExists('booking_add_ons');
        Schema::dropIfExists('maintenance_periods');
        Schema::dropIfExists('booking_holds');
    }
};
