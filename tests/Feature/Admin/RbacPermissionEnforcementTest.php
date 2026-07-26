<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacPermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_permission_cannot_discover_protected_admin_url(): void
    {
        $staff = User::factory()->create([
            'staff_role' => 'staff',
            'account_type' => 'staff',
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if (! route_has('azari.admin.reports.index')) {
            $this->markTestSkipped('Reports route is not registered.');
        }

        $this->actingAs($staff)->get(route('azari.admin.reports.index'))->assertNotFound();
    }

    public function test_granted_permission_allows_the_protected_admin_url(): void
    {
        $staff = User::factory()->create([
            'staff_role' => 'staff',
            'account_type' => 'staff',
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if (! route_has('azari.admin.reports.index')) {
            $this->markTestSkipped('Reports route is not registered.');
        }

        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'reports.view'],
            ['name' => 'View reports', 'group' => 'reports']
        );
        $staff->directPermissions()->sync([$permission->id => ['granted_by' => $staff->id]]);

        $this->actingAs($staff)->get(route('azari.admin.reports.index'))->assertOk();
    }
}

function route_has(string $name): bool
{
    return app('router')->has($name);
}
