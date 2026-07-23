<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\HomepageSection;
use App\Models\NavigationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintThreeFourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\AzariSprintThreeFourSeeder::class);
    }

    public function test_required_admin_is_seeded(): void
    {
        $admin = User::query()->where('email', 'admin@azariadmin.com')->firstOrFail();

        $this->assertSame('admin', $admin->username);
        $this->assertSame('administrator', $admin->staff_role);
        $this->assertTrue($admin->is_active);
    }

    public function test_cms_models_are_seeded(): void
    {
        $this->assertGreaterThan(0, NavigationItem::query()->count());
        $this->assertGreaterThan(0, HomepageSection::query()->count());
    }

    public function test_property_configuration_is_seeded(): void
    {
        $this->assertGreaterThan(0, Amenity::query()->count());
    }

    public function test_admin_can_open_cms_and_inventory(): void
    {
        $admin = User::query()->where('email', 'admin@azariadmin.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('azari.admin.cms.index'))
            ->assertSuccessful()
            ->assertSee('CMS and visual identity');

        $this->actingAs($admin)
            ->get(route('azari.admin.inventory.index'))
            ->assertSuccessful()
            ->assertSee('Property management');
    }
}
