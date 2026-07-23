<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'avatar_path' => fn (Blueprint $t) => $t->string('avatar_path')->nullable(),
            'account_type' => fn (Blueprint $t) => $t->string('account_type')->default('customer')->index(),
            'status' => fn (Blueprint $t) => $t->string('status')->default('active')->index(),
            'last_active_at' => fn (Blueprint $t) => $t->timestamp('last_active_at')->nullable(),
            'suspended_at' => fn (Blueprint $t) => $t->timestamp('suspended_at')->nullable(),
            'suspension_reason' => fn (Blueprint $t) => $t->text('suspension_reason')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $definition($table));
            }
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('group')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table): void {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->primary(['role_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table): void {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table): void {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('property_id')->index();
                $table->string('guest_name');
                $table->string('guest_email');
                $table->string('guest_phone')->nullable();
                $table->date('check_in')->index();
                $table->date('check_out')->index();
                $table->unsignedInteger('adults')->default(1);
                $table->unsignedInteger('children')->default(0);
                $table->unsignedInteger('rooms')->default(1);
                $table->string('status')->default('pending')->index();
                $table->string('verification_status')->default('unverified');
                $table->char('currency', 3)->default('NGN');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->text('guest_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_status_histories')) {
            Schema::create('booking_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blocked_dates')) {
            Schema::create('blocked_dates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_id')->index();
                $table->date('starts_on')->index();
                $table->date('ends_on')->index();
                $table->string('reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seasonal_prices')) {
            Schema::create('seasonal_prices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_id')->index();
                $table->string('name');
                $table->date('starts_on')->index();
                $table->date('ends_on')->index();
                $table->decimal('nightly_rate', 14, 2);
                $table->unsignedInteger('minimum_stay')->default(1);
                $table->unsignedInteger('maximum_stay')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_prices');
        Schema::dropIfExists('blocked_dates');
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
