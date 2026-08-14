<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
                $this->call(AzariCanonicalAdminSeeder::class);

$seeders = [
            AzariProductionSeeder::class,
            AzariSprintThreeFourSeeder::class,
            AzariSprintFiveSixDemoSeeder::class,
            AzariSprintSevenEightSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
