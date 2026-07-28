<?php

namespace Tests\Feature\Architecture;

use App\Models\Permission;
use Database\Seeders\AzariSprintSevenEightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PropertyOwnerPermissionsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_routes_are_registered(): void
    {
        foreach ([
            'public.list-property',
            'user.owner.dashboard',
            'user.owner.listings.index',
            'user.owner.listings.create',
            'user.owner.earnings',
            'user.owner.withdrawals',
            'azari.admin.owner-listings.index',
            'azari.admin.owner-withdrawals.index',
            'azari.admin.owner-settings.edit',
        ] as $route) {
            $this->assertTrue(Route::has($route), "Missing route {$route}");
        }
    }

    public function test_admin_owner_routes_separate_staff_and_permission_middleware(): void
    {
        $expectations = [
            'azari.admin.owner-listings.index' => 'azari.permission:property-owners.view',
            'azari.admin.owner-withdrawals.index' => 'azari.permission:owner-withdrawals.view',
            'azari.admin.owner-settings.edit' => 'azari.permission:owner-settings.manage',
        ];

        foreach ($expectations as $name => $permissionMiddleware) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];

            $this->assertContains('azari.staff', $middleware, "{$name} must remain staff protected");
            $this->assertContains($permissionMiddleware, $middleware, "{$name} has the wrong permission middleware");
            $this->assertFalse(
                collect($middleware)->contains(
                    fn (string $entry): bool => str_starts_with($entry, 'azari.staff:')
                ),
                "{$name} incorrectly uses azari.staff for a permission"
            );
        }
    }

    public function test_owner_permission_groups_are_seeded_for_staff_form_toggles(): void
    {
        $this->seed(AzariSprintSevenEightSeeder::class);

        foreach ([
            'property-owners.view',
            'property-owners.review',
            'owner-withdrawals.view',
            'owner-withdrawals.process',
            'owner-settings.manage',
        ] as $slug) {
            $this->assertTrue(
                Permission::query()->where('slug', $slug)->exists(),
                "Missing permission {$slug}"
            );
        }
    }
}
