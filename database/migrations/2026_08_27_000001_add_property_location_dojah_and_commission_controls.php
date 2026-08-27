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
            $table->text('formatted_address')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('address_city', 120)->nullable();
            $table->string('address_region', 120)->nullable();
            $table->string('address_postal_code', 40)->nullable();
            $table->string('address_country_code', 2)->nullable();
            $table->string('google_place_id', 255)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('property_name_snapshot')->nullable();
            $table->text('property_formatted_address')->nullable();
            $table->string('property_google_place_id', 255)->nullable();
            $table->decimal('property_latitude', 10, 7)->nullable();
            $table->decimal('property_longitude', 10, 7)->nullable();
        });

        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_guest_id')->nullable()->constrained('booking_guests')->nullOnDelete();
            $table->string('provider', 32)->default('dojah')->index();
            $table->uuid('reference')->unique();
            $table->string('provider_event_id')->nullable()->index();
            $table->string('widget_id')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('provider_status', 80)->nullable();
            $table->string('verification_type', 80)->nullable();
            $table->string('verification_mode', 80)->nullable();
            $table->text('verification_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->string('last_payload_hash', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'provider', 'status']);
            $table->index(['booking_guest_id', 'provider', 'status']);
        });

        if (Schema::hasTable('owner_ledger_entries')) {
            Schema::table('owner_ledger_entries', function (Blueprint $table): void {
                $table->decimal('azari_share_percentage', 5, 2)->nullable();
                $table->decimal('azari_share_amount', 18, 2)->nullable();
            });
        }

        if (Schema::hasTable('site_settings')) {
            foreach ([
                'owner_default_share_percentage' => ['value' => '88', 'type' => 'number'],
                'owner_withdrawal_currency' => ['value' => 'USD', 'type' => 'text'],
            ] as $key => $setting) {
                $existing = DB::table('site_settings')->where('key', $key)->exists();
                $values = [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => 'property_owners',
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('site_settings')->where('key', $key)->update($values);
                } else {
                    DB::table('site_settings')->insert($values + ['key' => $key, 'created_at' => now()]);
                }
            }
        }

        DB::table('properties')
            ->where('ownership_type', 'third_party')
            ->whereNull('owner_share_percentage')
            ->update(['owner_share_percentage' => 88]);
    }

    public function down(): void
    {
        if (Schema::hasTable('owner_ledger_entries')) {
            Schema::table('owner_ledger_entries', function (Blueprint $table): void {
                $table->dropColumn(['azari_share_percentage', 'azari_share_amount']);
            });
        }

        Schema::dropIfExists('identity_verifications');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'property_name_snapshot',
                'property_formatted_address',
                'property_google_place_id',
                'property_latitude',
                'property_longitude',
            ]);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn([
                'formatted_address',
                'address_line_1',
                'address_line_2',
                'address_city',
                'address_region',
                'address_postal_code',
                'address_country_code',
                'google_place_id',
                'latitude',
                'longitude',
            ]);
        });
    }
};
