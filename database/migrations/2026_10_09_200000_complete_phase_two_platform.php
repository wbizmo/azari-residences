<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_staff_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->json('capabilities')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'user_id']);
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('property_staff_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 40);
            $table->json('capabilities')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'email']);
        });

        Schema::create('property_operations_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 40)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status', 'due_at']);
        });

        Schema::create('booking_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique('booking_id');
            $table->index(['property_id', 'last_message_at']);
        });

        Schema::create('booking_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('booking_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 20);
            $table->text('body');
            $table->string('locale', 12)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('web_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'endpoint']);
        });

        Schema::table('inventory_change_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_change_logs', 'before_snapshot')) {
                $table->json('before_snapshot')->nullable();
            }
            if (! Schema::hasColumn('inventory_change_logs', 'reverted_at')) {
                $table->timestamp('reverted_at')->nullable();
            }
            if (! Schema::hasColumn('inventory_change_logs', 'reverted_by')) {
                $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'severity')) {
                $table->string('severity', 30)->default('general')->after('status');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('severity');
            }
            if (! Schema::hasColumn('support_tickets', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('sla_due_at');
            }
        });

        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'edited_at')) {
                $table->timestamp('edited_at')->nullable();
            }
            if (! Schema::hasColumn('reviews', 'owner_reply')) {
                $table->text('owner_reply')->nullable();
            }
            if (! Schema::hasColumn('reviews', 'owner_replied_at')) {
                $table->timestamp('owner_replied_at')->nullable();
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'arrival_time')) {
                $table->time('arrival_time')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'arrival_notes')) {
                $table->text('arrival_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['arrival_time', 'arrival_notes'] as $column) {
                if (Schema::hasColumn('bookings', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('reviews', function (Blueprint $table): void {
            foreach (['edited_at', 'owner_reply', 'owner_replied_at'] as $column) {
                if (Schema::hasColumn('reviews', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('inventory_change_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_change_logs', 'reverted_by')) {
                $table->dropConstrainedForeignId('reverted_by');
            }
            foreach (['before_snapshot', 'reverted_at'] as $column) {
                if (Schema::hasColumn('inventory_change_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('support_tickets', function (Blueprint $table): void {
            foreach (['severity', 'sla_due_at', 'escalated_at'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) $table->dropColumn($column);
            }
        });

        Schema::dropIfExists('web_push_subscriptions');
        Schema::dropIfExists('booking_messages');
        Schema::dropIfExists('booking_conversations');
        Schema::dropIfExists('property_operations_tasks');
        Schema::dropIfExists('property_staff_invitations');
        Schema::dropIfExists('property_staff_memberships');
    }
};
