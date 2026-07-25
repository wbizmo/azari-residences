<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 80)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable();
            }
            if (! Schema::hasColumn('users', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 50)->nullable();
            }
            if (! Schema::hasColumn('users', 'email_notifications')) {
                $table->boolean('email_notifications')->default(true);
            }
            if (! Schema::hasColumn('users', 'sms_notifications')) {
                $table->boolean('sms_notifications')->default(false);
            }
            if (! Schema::hasColumn('users', 'marketing_consent')) {
                $table->boolean('marketing_consent')->default(false);
            }
        });

        if (! Schema::hasTable('permission_user')) {
            Schema::create('permission_user', function (Blueprint $table): void {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->primary(['permission_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('staff_login_histories')) {
            Schema::create('staff_login_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('result', 30)->default('success')->index();
                $table->timestamp('logged_in_at')->nullable()->index();
                $table->timestamp('logged_out_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 120)->index();
                $table->string('subject_type')->nullable()->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->string('request_id', 80)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! Schema::hasTable('identity_types')) {
            Schema::create('identity_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_identity_documents')) {
            Schema::create('user_identity_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('identity_type_id')->nullable()->constrained('identity_types')->nullOnDelete();
                $table->string('document_type', 60);
                $table->string('disk', 40)->default('private');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes');
                $table->string('sha256', 64)->index();
                $table->boolean('is_current')->default(true)->index();
                $table->foreignId('replaces_id')->nullable()->constrained('user_identity_documents')->nullOnDelete();
                $table->timestamp('replaced_at')->nullable();
                $table->string('review_status', 30)->default('pending')->index();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'is_current']);
            });
        }

        if (Schema::hasTable('guest_identity_documents')) {
            Schema::table('guest_identity_documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('guest_identity_documents', 'review_status')) {
                    $table->string('review_status', 30)->default('pending')->index();
                }
                if (! Schema::hasColumn('guest_identity_documents', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('guest_identity_documents', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable();
                }
                if (! Schema::hasColumn('guest_identity_documents', 'review_note')) {
                    $table->text('review_note')->nullable();
                }
            });
        }

        if (! Schema::hasTable('booking_identity_links')) {
            Schema::create('booking_identity_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->foreignId('booking_guest_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('user_identity_document_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('guest_identity_document_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_booking_owner')->default(false)->index();
                $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['booking_id', 'booking_guest_id'], 'booking_guest_identity_unique');
            });
        }

        if (! Schema::hasTable('identity_audit_histories')) {
            Schema::create('identity_audit_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('document_type', 40);
                $table->unsignedBigInteger('document_id');
                $table->string('action', 80)->index();
                $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['document_type', 'document_id']);
            });
        }

        if (! Schema::hasTable('phone_verification_codes')) {
            Schema::create('phone_verification_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('phone', 50);
                $table->string('code_hash');
                $table->timestamp('expires_at')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->string('reference')->unique();
                $table->string('provider_reference')->nullable()->index();
                $table->string('provider', 30)->index();
                $table->foreignId('booking_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('guest_email')->nullable()->index();
                $table->decimal('amount', 14, 2);
                $table->char('currency', 3);
                $table->string('status', 30)->default('initiated')->index();
                $table->string('payment_method', 80)->nullable();
                $table->string('checkout_url', 2048)->nullable();
                $table->timestamp('initiated_at')->nullable();
                $table->timestamp('paid_at')->nullable()->index();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('abandoned_at')->nullable();
                $table->json('provider_response_summary')->nullable();
                $table->json('safe_metadata')->nullable();
                $table->string('receipt_number')->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('creation_source', 30)->default('system');
                $table->string('proof_disk', 40)->nullable();
                $table->string('proof_path')->nullable();
                $table->text('administrative_note')->nullable();
                $table->timestamps();
                $table->index(['booking_id', 'status']);
                $table->unique(['provider', 'provider_reference'], 'payments_provider_reference_unique');
            });
        }

        if (! Schema::hasTable('payment_events')) {
            Schema::create('payment_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
                $table->string('provider', 30)->index();
                $table->string('event_id', 160);
                $table->string('event_type', 80)->nullable()->index();
                $table->string('source', 30)->index();
                $table->boolean('signature_valid')->nullable();
                $table->boolean('processed')->default(false)->index();
                $table->timestamp('received_at')->index();
                $table->timestamp('processed_at')->nullable();
                $table->json('safe_payload')->nullable();
                $table->text('safe_error')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'event_id']);
            });
        }

        if (! Schema::hasTable('payment_verification_attempts')) {
            Schema::create('payment_verification_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 30)->index();
                $table->string('result', 40)->index();
                $table->string('provider_status', 80)->nullable();
                $table->decimal('reported_amount', 14, 2)->nullable();
                $table->char('reported_currency', 3)->nullable();
                $table->string('reported_reference')->nullable();
                $table->text('safe_error')->nullable();
                $table->json('safe_response')->nullable();
                $table->timestamp('attempted_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_provider_statuses')) {
            Schema::create('payment_provider_statuses', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 30)->unique();
                $table->string('mode', 20)->nullable();
                $table->boolean('enabled')->default(false);
                $table->string('connection_status', 30)->default('not_tested')->index();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('last_webhook_at')->nullable();
                $table->timestamp('last_successful_payment_at')->nullable();
                $table->text('safe_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'cancellation_internal_note')) {
                $table->text('cancellation_internal_note')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'cancellation_payment_note')) {
                $table->text('cancellation_payment_note')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'external_refund_reference')) {
                $table->string('external_refund_reference')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('guest_identity_documents')) {
            Schema::table('guest_identity_documents', function (Blueprint $table): void {
                if (Schema::hasColumn('guest_identity_documents', 'reviewed_by')) {
                    $table->dropConstrainedForeignId('reviewed_by');
                }
                foreach (['review_status', 'reviewed_at', 'review_note'] as $column) {
                    if (Schema::hasColumn('guest_identity_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payment_provider_statuses');
        Schema::dropIfExists('payment_verification_attempts');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('phone_verification_codes');
        Schema::dropIfExists('identity_audit_histories');
        Schema::dropIfExists('booking_identity_links');
        Schema::dropIfExists('user_identity_documents');
        Schema::dropIfExists('identity_types');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('staff_login_histories');
        Schema::dropIfExists('permission_user');
    }
};
