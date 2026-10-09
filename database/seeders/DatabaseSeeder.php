<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production seeding must never create demo credentials or populate
        // live booking inventory with example data. An explicitly configured
        // bootstrap administrator is the only permitted default seed action.
        if (app()->environment('production')) {
            $this->call(AzariCanonicalAdminSeeder::class);
            return;
        }

        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Demo seeders are restricted to local and testing environments.');
            return;
        }

        $this->call(AzariCanonicalAdminSeeder::class);

        foreach ([
            AzariProductionSeeder::class,
            AzariSprintThreeFourSeeder::class,
            AzariSprintFiveSixDemoSeeder::class,
            AzariSprintSevenEightSeeder::class,
        ] as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
