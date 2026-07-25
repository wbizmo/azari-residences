<?php

namespace Database\Seeders;

use App\Models\IdentityType;
use App\Models\PaymentProviderStatus;
use App\Models\Permission;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class AzariSprintSevenEightSeeder extends Seeder
{
    public function run(): void
    {
        $identityTypes = [
            ['name' => 'Passport', 'slug' => 'passport', 'description' => 'Government-issued passport.', 'is_system' => true],
            ['name' => 'National ID', 'slug' => 'national_id', 'description' => 'Government-issued national identity card.', 'is_system' => true],
            ['name' => "Driver's Licence", 'slug' => 'drivers_licence', 'description' => "Government-issued driver's licence.", 'is_system' => true],
            ['name' => 'Other government ID', 'slug' => 'other_government_id', 'description' => 'An administrator-approved government-issued photo ID.', 'is_system' => true],
        ];
        foreach ($identityTypes as $index => $type) {
            IdentityType::query()->updateOrCreate(['slug' => $type['slug']], [...$type, 'is_active' => true, 'sort_order' => $index + 1]);
        }

        $modules = [
            'dashboard' => 'Dashboard', 'bookings' => 'Bookings', 'payments' => 'Payments',
            'properties' => 'Properties', 'availability' => 'Availability', 'guests' => 'Guests',
            'guest-identities' => 'Guest identities', 'service-requests' => 'Service requests',
            'support-tickets' => 'Support tickets', 'documents' => 'Documents',
            'communications' => 'Communications', 'cms' => 'CMS', 'reports' => 'Reports',
            'staff' => 'Staff', 'settings' => 'Settings', 'audit-logs' => 'Audit logs',
            'system-health' => 'System health',
        ];
        foreach ($modules as $module => $label) {
            foreach (['view', 'create', 'edit', 'delete', 'export', 'manage'] as $action) {
                Permission::query()->updateOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['name' => $label.' — '.ucfirst($action), 'group' => $label],
                );
            }
        }

        SystemSetting::query()->whereIn('key', [
            'paystack_public_key', 'paystack_secret_key', 'stripe_public_key', 'stripe_secret_key',
            'flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_encryption_key', 'flutterwave_webhook_secret',
            'pesapal_consumer_key', 'pesapal_consumer_secret', 'pesapal_notification_id',
            'intouch_merchant_id', 'intouch_username', 'intouch_password', 'intouch_secret', 'intouch_webhook_secret',
            'twilio_sid', 'twilio_token', 'twilio_from',
        ])->delete();

        foreach (['flutterwave', 'pesapal', 'intouch'] as $provider) {
            PaymentProviderStatus::query()->updateOrCreate(
                ['provider' => $provider],
                ['enabled' => (bool) config("azari.payments.{$provider}.enabled"), 'mode' => config("azari.payments.{$provider}.mode"), 'connection_status' => 'not_tested'],
            );
        }

        $supportPermissions = Permission::query()->whereIn('slug', [
            'dashboard.view', 'bookings.view', 'payments.view', 'guests.view',
            'guest-identities.view', 'guest-identities.edit', 'support-tickets.view',
            'support-tickets.edit', 'service-requests.view', 'service-requests.edit',
        ])->pluck('id');
        User::query()->where('staff_role', 'support')->each(function (User $user) use ($supportPermissions): void {
            if ($user->directPermissions()->count() === 0) {
                $user->directPermissions()->sync($supportPermissions->mapWithKeys(fn ($id) => [$id => ['granted_by' => null]])->all());
            }
        });
    }
}
