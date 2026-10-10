<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyStaffMembership;
use App\Models\User;
use App\Services\Owners\PropertyAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerStaffRoleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegated_manager_cannot_invite_finance_staff_change_roles_or_revoke_memberships(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $finance = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $membership = PropertyStaffMembership::query()->create([
            'property_id' => $property->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'accepted_at' => now(),
        ]);

        $this->actingAs($manager)->post(route('user.owner.phase2.staff.invite', $property), [
            'email' => $finance->email,
            'role' => 'finance_viewer',
        ])->assertNotFound();

        $this->actingAs($manager)->patch(
            route('user.owner.phase2.staff.update', [$property, $membership]),
            ['role' => 'finance_viewer']
        )->assertNotFound();

        $this->actingAs($manager)->delete(
            route('user.owner.phase2.staff.revoke', [$property, $membership])
        )->assertNotFound();

        $this->assertSame('manager', $membership->fresh()->role);
        $this->assertNull($membership->fresh()->revoked_at);
        $this->assertDatabaseCount('property_staff_invitations', 0);
    }

    public function test_owner_role_change_revokes_previous_capabilities_immediately(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $membership = PropertyStaffMembership::query()->create([
            'property_id' => $property->id,
            'user_id' => $staff->id,
            'role' => 'inventory_editor',
            'capabilities' => ['inventory.manage', 'operations.manage'],
            'accepted_at' => now(),
        ]);
        $permissions = app(PropertyAccessService::class);
        $this->assertTrue($permissions->can($staff, $property, 'inventory.manage'));

        $this->actingAs($owner)->patch(
            route('user.owner.phase2.staff.update', [$property, $membership]),
            ['role' => 'finance_viewer']
        )->assertRedirect();

        $this->assertSame('finance_viewer', $membership->fresh()->role);
        $this->assertSame([], $membership->fresh()->capabilities ?? []);
        $this->assertFalse($permissions->can($staff, $property, 'inventory.manage'));
        $this->assertFalse($permissions->can($staff, $property, 'operations.manage'));
        $this->assertTrue($permissions->can($staff, $property, 'finance.view'));
    }
}
