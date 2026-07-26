<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * These values may exceed VARCHAR(255) in production:
         * uploaded paths, original filenames, external URLs and
         * third-party provider references.
         *
         * TEXT is supported by both SQLite and MySQL.
         */

        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('support_ticket_messages', 'attachment_path')) {
                    $table->text('attachment_path')->nullable()->change();
                }

                if (Schema::hasColumn('support_ticket_messages', 'attachment_name')) {
                    $table->text('attachment_name')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('service_requests')) {
            Schema::table('service_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('service_requests', 'attachment_path')) {
                    $table->text('attachment_path')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('promotions')) {
            Schema::table('promotions', function (Blueprint $table): void {
                if (Schema::hasColumn('promotions', 'cta_url')) {
                    $table->text('cta_url')->nullable()->change();
                }

                if (Schema::hasColumn('promotions', 'image_path')) {
                    $table->text('image_path')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table): void {
                if (Schema::hasColumn('properties', 'video_url')) {
                    $table->text('video_url')->nullable()->change();
                }

                if (Schema::hasColumn('properties', 'virtual_tour_url')) {
                    $table->text('virtual_tour_url')->nullable()->change();
                }

                if (Schema::hasColumn('properties', 'cover_image')) {
                    $table->text('cover_image')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('property_media')) {
            Schema::table('property_media', function (Blueprint $table): void {
                if (Schema::hasColumn('property_media', 'path')) {
                    $table->text('path')->change();
                }

                if (Schema::hasColumn('property_media', 'alt_text')) {
                    $table->text('alt_text')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('user_identity_documents')) {
            Schema::table('user_identity_documents', function (Blueprint $table): void {
                if (Schema::hasColumn('user_identity_documents', 'path')) {
                    $table->text('path')->change();
                }

                if (Schema::hasColumn('user_identity_documents', 'original_name')) {
                    $table->text('original_name')->change();
                }
            });
        }

        if (Schema::hasTable('guest_identity_documents')) {
            Schema::table('guest_identity_documents', function (Blueprint $table): void {
                if (Schema::hasColumn('guest_identity_documents', 'path')) {
                    $table->text('path')->change();
                }

                if (Schema::hasColumn('guest_identity_documents', 'original_name')) {
                    $table->text('original_name')->change();
                }
            });
        }

        if (Schema::hasTable('communication_logs')) {
            Schema::table('communication_logs', function (Blueprint $table): void {
                if (Schema::hasColumn('communication_logs', 'provider_reference')) {
                    $table->string('provider_reference', 512)->nullable()->change();
                }

                if (Schema::hasColumn('communication_logs', 'recipient')) {
                    $table->string('recipient', 512)->change();
                }

                if (Schema::hasColumn('communication_logs', 'masked_recipient')) {
                    $table->string('masked_recipient', 512)->nullable()->change();
                }
            });
        }

        /*
         * payments.provider_reference participates in an index/unique
         * constraint. Keep it index-safe for utf8mb4 MySQL while allowing
         * more room than the default VARCHAR(255) is unnecessary.
         *
         * checkout_url is already VARCHAR(2048), so it is left unchanged.
         */
    }

    public function down(): void
    {
        /*
         * Deliberately non-destructive.
         *
         * Shrinking these columns could truncate legitimate production data.
         * Leaving them widened is safe on both SQLite and MySQL.
         */
    }
};
