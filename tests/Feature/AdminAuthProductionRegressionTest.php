<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthProductionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_browser_is_redirected_to_dedicated_admin_login(): void
    {
        $this->get('/azaridevadmin')
            ->assertRedirect(route('azari.admin.login'));
    }

    public function test_anonymous_json_request_receives_401(): void
    {
        $this->getJson('/azaridevadmin')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_authenticated_customer_cannot_discover_admin_dashboard(): void
    {
        $customer = User::factory()->create([
            'account_type' => 'customer',
            'is_admin' => false,
            'staff_role' => null,
            'is_active' => true,
            'status' => 'active',
            'suspended_at' => null,
        ]);

        $this->actingAs($customer)
            ->get('/azaridevadmin')
            ->assertNotFound();
    }

    public function test_administrator_can_login_with_login_field_and_reach_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-regression@example.test',
            'password' => Hash::make('12345678'),
            'account_type' => 'staff',
            'is_admin' => true,
            'staff_role' => 'administrator',
            'is_active' => true,
            'status' => 'active',
            'suspended_at' => null,
            'email_verified_at' => now(),
        ]);

        $this->post(route('azari.admin.login.store'), [
            'login' => $admin->email,
            'password' => '12345678',
        ])->assertRedirect(route('azari.admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->get('/azaridevadmin')->assertOk();
    }

    public function test_canonical_admin_seeder_provisions_demo_admin_in_testing(): void
    {
        $this->seed(\Database\Seeders\AzariCanonicalAdminSeeder::class);

        $admin = User::query()
            ->where('email', 'admin@azaridevadmin.com')
            ->firstOrFail();

        $this->assertTrue($admin->isStaff());
        $this->assertTrue($admin->isAdministrator());
        $this->assertFalse($admin->isSuspended());
        $this->assertTrue(Hash::check('12345678', $admin->password));
    }
}
