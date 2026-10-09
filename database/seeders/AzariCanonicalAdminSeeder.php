<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AzariCanonicalAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('AZARI_BOOTSTRAP_ADMIN_EMAIL', ''));
        $password = (string) env('AZARI_BOOTSTRAP_ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            if (! app()->environment('local', 'testing')) {
                $this->command?->warn(
                    'Canonical administrator provisioning skipped: explicitly set AZARI_BOOTSTRAP_ADMIN_EMAIL and AZARI_BOOTSTRAP_ADMIN_PASSWORD.'
                );

                return;
            }

            $email = 'admin@azaridevadmin.com';
            $password = '12345678';
        }

        if (! app()->environment('local', 'testing') && strlen($password) < 14) {
            throw new \RuntimeException('Production bootstrap administrator requires a strong password of at least 14 characters.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $values = [
            'name' => 'Azari Administrator',
            'email' => $email,
            'password' => Hash::make($password),
        ];

        if (Schema::hasColumn('users', 'username')) {
            $username = 'azaridevadmin';

            $collision = User::query()
                ->where('username', $username)
                ->where('email', '!=', $email)
                ->exists();

            if ($collision) {
                $username .= '_'.substr(md5($email), 0, 6);
            }

            $values['username'] = $username;
        }

        $optional = [
            'account_type' => 'staff',
            'is_admin' => true,
            'staff_role' => 'administrator',
            'is_active' => true,
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
            'email_verified_at' => now(),
        ];

        foreach ($optional as $column => $value) {
            if (Schema::hasColumn('users', $column)) {
                $values[$column] = $value;
            }
        }

        $user->forceFill($values);
        $user->save();
    }
}
