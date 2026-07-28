<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            if (! Schema::hasColumn('properties', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('properties', 'owner_listing_id')) {
                $table->unsignedBigInteger('owner_listing_id')->nullable()->after('owner_id')->index();
            }
            if (! Schema::hasColumn('properties', 'owner_share_percentage')) {
                $table->decimal('owner_share_percentage', 5, 2)->default(0)->after('owner_listing_id');
            }
            if (! Schema::hasColumn('properties', 'managed_for_owner')) {
                $table->boolean('managed_for_owner')->default(false)->after('owner_share_percentage');
            }
        });

        Schema::create('listing_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version', 40);
            $table->string('legal_name');
            $table->longText('agreement_text');
            $table->string('signature_hash', 64);
            $table->ipAddress('signed_ip')->nullable();
            $table->text('signed_user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['user_id', 'version']);
        });

        Schema::create('property_listings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_agreement_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->json('property_data');
            $table->json('amenity_ids')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->decimal('proposed_owner_share_percentage', 5, 2)->nullable();
            $table->decimal('approved_owner_share_percentage', 5, 2)->nullable();
            $table->text('owner_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('owner_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('withdrawal_request_id')->nullable()->index();
            $table->string('type', 32);
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->decimal('gross_amount', 14, 2)->nullable();
            $table->decimal('owner_share_percentage', 5, 2)->nullable();
            $table->string('reference', 60)->unique();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('payment_id');
            $table->index(['user_id', 'currency', 'created_at']);
        });

        Schema::create('owner_payout_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('preferred_gateway', 20)->default('paypal');
            $table->string('paypal_recipient')->nullable();
            $table->string('paypal_recipient_type', 20)->default('EMAIL');
            $table->string('stripe_connected_account_id')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('currency', 3);
            $table->decimal('amount', 14, 2);
            $table->string('status', 24)->default('pending')->index();
            $table->json('destination_snapshot');
            $table->string('provider_reference')->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->text('owner_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        foreach ([
            ['property-owners.view', 'View property owner submissions'],
            ['property-owners.review', 'Approve or decline property listings'],
            ['owner-withdrawals.view', 'View owner withdrawals'],
            ['owner-withdrawals.process', 'Process owner withdrawals'],
            ['owner-settings.manage', 'Manage owner marketplace settings'],
        ] as [$slug, $name]) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => $name, 'module' => str_contains($slug, 'withdrawal') ? 'owner-withdrawals' : 'property-owners', 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (Schema::hasTable('site_settings')) {
            foreach ([
                ['owner_listing_agreement_version', '1.0', 'text', 'property_owners'],
                ['owner_default_share_percentage', '70', 'number', 'property_owners'],
                ['owner_withdrawal_days', '1,2,3,4,5', 'text', 'property_owners'],
                ['owner_withdrawal_minimum', '50', 'number', 'property_owners'],
                ['owner_withdrawal_currency', 'USD', 'text', 'property_owners'],
                ['owner_paypal_enabled', '0', 'boolean', 'property_owners'],
                ['owner_stripe_enabled', '0', 'boolean', 'property_owners'],
            ] as [$key, $value, $type, $group]) {
                DB::table('site_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'type' => $type, 'group' => $group, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('owner_payout_profiles');
        Schema::dropIfExists('owner_ledger_entries');
        Schema::dropIfExists('property_listings');
        Schema::dropIfExists('listing_agreements');

        Schema::table('properties', function (Blueprint $table): void {
            foreach (['owner_id', 'owner_listing_id', 'owner_share_percentage', 'managed_for_owner'] as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
