<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'phone')) $table->string('phone', 40)->nullable();
            if (! Schema::hasColumn('users', 'account_type')) $table->string('account_type', 30)->default('customer')->index();
            if (! Schema::hasColumn('users', 'status')) $table->string('status', 30)->default('active')->index();
            if (! Schema::hasColumn('users', 'avatar_path')) $table->string('avatar_path')->nullable();
            if (! Schema::hasColumn('users', 'last_active_at')) $table->timestamp('last_active_at')->nullable();
            if (! Schema::hasColumn('users', 'suspended_at')) $table->timestamp('suspended_at')->nullable();
            if (! Schema::hasColumn('users', 'suspension_reason')) $table->text('suspension_reason')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'paid_at')) $table->timestamp('paid_at')->nullable()->index();
            if (! Schema::hasColumn('bookings', 'receipt_number')) $table->string('receipt_number')->nullable()->unique();
            if (! Schema::hasColumn('bookings', 'payment_reference')) $table->string('payment_reference')->nullable()->unique();
            if (! Schema::hasColumn('bookings', 'cancellation_reason')) $table->text('cancellation_reason')->nullable();
            if (! Schema::hasColumn('bookings', 'checked_in_at')) $table->timestamp('checked_in_at')->nullable();
            if (! Schema::hasColumn('bookings', 'completed_at')) $table->timestamp('completed_at')->nullable();
        });

        Schema::table('guest_identity_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('guest_identity_documents', 'review_status')) $table->string('review_status', 30)->default('pending')->index();
            if (! Schema::hasColumn('guest_identity_documents', 'reviewed_by')) $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('guest_identity_documents', 'reviewed_at')) $table->timestamp('reviewed_at')->nullable();
            if (! Schema::hasColumn('guest_identity_documents', 'review_note')) $table->text('review_note')->nullable();
        });
    }

    public function down(): void {}
};
