<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVouchersToAzariBookings extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name', 160);
                $table->enum('discount_type', ['percentage', 'fixed']);
                $table->decimal('discount_value', 14, 2);
                $table->decimal('maximum_discount', 14, 2)->nullable();
                $table->decimal('minimum_booking_value', 14, 2)->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('total_usage_limit')->nullable();
                $table->unsignedInteger('per_customer_limit')->default(1);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voucher_property')) {
            Schema::create('voucher_property', function (Blueprint $table): void {
                $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->primary(['voucher_id', 'property_id']);
            });
        }

        if (! Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
                $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('guest_email', 190)->nullable()->index();
                $table->decimal('discount_amount', 14, 2);
                $table->timestamp('redeemed_at');
                $table->timestamps();
            });
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'voucher_id')) $table->foreignId('voucher_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('bookings', 'voucher_code')) $table->string('voucher_code', 64)->nullable();
            if (! Schema::hasColumn('bookings', 'discount_total')) $table->decimal('discount_total', 14, 2)->default(0);
            if (! Schema::hasColumn('bookings', 'voucher_snapshot')) $table->json('voucher_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['voucher_id', 'voucher_code', 'discount_total', 'voucher_snapshot'] as $column) {
                if (Schema::hasColumn('bookings', $column)) $table->dropColumn($column);
            }
        });
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('voucher_property');
        Schema::dropIfExists('vouchers');
    }
};
