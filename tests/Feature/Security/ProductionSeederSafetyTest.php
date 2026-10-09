<?php

namespace Tests\Feature\Security;

use Database\Seeders\AzariSprintFiveSixDemoSeeder;
use Database\Seeders\AzariSprintThreeFourSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_demo_seeders_cannot_create_fixed_password_admins_in_production(): void
    {
        $old = app()->environment();
        try {
            app()->detectEnvironment(fn () => 'production');
            (new AzariSprintThreeFourSeeder())->run();
            (new AzariSprintFiveSixDemoSeeder())->run();
            $this->assertDatabaseMissing('users', ['email' => 'admin@azariadmin.com']);
            $this->assertDatabaseMissing('users', ['email' => 'user@example.com']);
        } finally {
            app()->detectEnvironment(fn () => $old);
        }
    }
}
