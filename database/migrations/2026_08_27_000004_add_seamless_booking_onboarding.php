<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_holds', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_holds', 'guest_draft')) {
                $table->json('guest_draft')->nullable();
            }
            if (! Schema::hasColumn('booking_holds', 'email_code_hash')) {
                $table->string('email_code_hash')->nullable();
            }
            if (! Schema::hasColumn('booking_holds', 'email_code_expires_at')) {
                $table->timestamp('email_code_expires_at')->nullable();
            }
            if (! Schema::hasColumn('booking_holds', 'email_code_sent_at')) {
                $table->timestamp('email_code_sent_at')->nullable();
            }
            if (! Schema::hasColumn('booking_holds', 'email_code_attempts')) {
                $table->unsignedTinyInteger('email_code_attempts')->default(0);
            }
            if (! Schema::hasColumn('booking_holds', 'email_code_locked_until')) {
                $table->timestamp('email_code_locked_until')->nullable();
            }
        });

        Schema::table('booking_guests', function (Blueprint $table): void {
            if (! Schema::hasColumn('booking_guests', 'email')) {
                $table->string('email')->nullable()->index();
            }
            if (! Schema::hasColumn('booking_guests', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        if (! Schema::hasTable('booking_guest_verification_invites')) {
            Schema::create('booking_guest_verification_invites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('booking_guest_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('email')->index();
                $table->string('code_hash')->nullable();
                $table->timestamp('code_expires_at')->nullable();
                $table->timestamp('code_sent_at')->nullable();
                $table->unsignedTinyInteger('code_attempts')->default(0);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('invite_sent_at')->nullable();
                $table->unsignedInteger('invite_count')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_guest_verification_invites');

        Schema::table('booking_guests', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_guests', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('booking_guests', 'email')) {
                $table->dropColumn('email');
            }
        });

        Schema::table('booking_holds', function (Blueprint $table): void {
            $columns = [
                'guest_draft',
                'email_code_hash',
                'email_code_expires_at',
                'email_code_sent_at',
                'email_code_attempts',
                'email_code_locked_until',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('booking_holds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
