<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\AzariSprintSevenEightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintSevenAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AzariSprintSevenEightSeeder::class);
    }

    public function test_normal_user_receives_404_for_staff_and_admin_routes(): void
    {
        $customer = User::factory()->create(['account_type' => 'customer', 'is_admin' => false, 'staff_role' => null, 'is_active' => true]);

        $this->actingAs($customer)->get(route('azari.admin.login'))->assertNotFound();
        $this->actingAs($customer)->get(route('azari.admin.dashboard'))->assertNotFound();
        $this->actingAs($customer)->getJson(route('azari.admin.payments.index'))->assertNotFound();
    }

    public function test_staff_and_administrator_receive_403_for_user_only_routes(): void
    {
        $administrator = User::factory()->create(['account_type' => 'staff', 'is_admin' => true, 'staff_role' => 'administrator', 'is_active' => true]);
        $staff = User::factory()->create(['account_type' => 'staff', 'is_admin' => false, 'staff_role' => 'staff', 'is_active' => true]);

        $this->actingAs($administrator)->get(route('user.dashboard'))->assertForbidden();
        $this->actingAs($staff)->getJson(route('user.payments.index'))->assertForbidden();
    }

    public function test_unauthenticated_user_routes_redirect_or_return_401_json(): void
    {
        $this->get(route('user.dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('user.dashboard'))->assertUnauthorized();
    }

    public function test_staff_module_permission_is_enforced_server_side(): void
    {
        $staff = User::factory()->create(['account_type' => 'staff', 'is_admin' => false, 'staff_role' => 'staff', 'is_active' => true]);

        $this->actingAs($staff)->get(route('azari.admin.payments.index'))->assertNotFound();

        $permission = Permission::query()->where('slug', 'payments.view')->firstOrFail();
        $staff->directPermissions()->attach($permission->id, ['granted_by' => null]);
        $this->actingAs($staff->fresh())->get(route('azari.admin.payments.index'))->assertOk();
    }
}
