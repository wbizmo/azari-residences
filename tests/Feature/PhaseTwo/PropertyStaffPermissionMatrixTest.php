<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyStaffMembership;
use App\Models\User;
use App\Services\Owners\PropertyAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyStaffPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Property $property, string $role): User
    {
        $staff = User::factory()->create(['email_verified_at' => now()]);
        PropertyStaffMembership::query()->create([
            'property_id' => $property->id,
            'user_id' => $staff->id,
            'role' => $role,
            'capabilities' => [],
            'accepted_at' => now(),
        ]);
        return $staff;
    }

    public function test_inventory_and_finance_only_roles_cannot_open_staff_operations_or_messages(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        foreach (['inventory_editor', 'finance_viewer'] as $role) {
            $actor = $this->staff($property, $role);
            $this->actingAs($actor)
                ->get(route('user.owner.phase2.staff', $property))->assertNotFound();
            $this->actingAs($actor)
                ->get(route('user.owner.phase2.operations', $property))->assertNotFound();
            $this->actingAs($actor)
                ->get(route('user.owner.phase2.messages', $property))->assertNotFound();
            $this->actingAs($actor)
                ->get(route('user.owner.phase2.reviews', $property))->assertNotFound();
        }
    }

    public function test_support_agent_cannot_manage_operational_staff_or_inventory(): void
    {
        $property = Property::factory()->create(['owner_id' => User::factory()->create()->id]);
        $agent = $this->staff($property, 'support_agent');
        $this->actingAs($agent)
            ->get(route('user.owner.phase2.messages', $property))->assertOk();
        $this->actingAs($agent)
            ->get(route('user.owner.phase2.operations', $property))->assertNotFound();
        $this->actingAs($agent)
            ->get(route('user.owner.phase2.staff', $property))->assertNotFound();

        $permissions = app(PropertyAccessService::class);
        $this->assertFalse($permissions->can($agent, $property, 'inventory.manage'));
        $this->assertFalse($permissions->can($agent, $property, 'finance.view'));
    }

    public function test_property_membership_never_grants_access_to_another_property_or_after_revocation(): void
    {
        $propertyA = Property::factory()->create(['owner_id' => User::factory()->create()->id]);
        $propertyB = Property::factory()->create(['owner_id' => User::factory()->create()->id]);
        $manager = $this->staff($propertyA, 'manager');

        $this->actingAs($manager)
            ->get(route('user.owner.phase2.operations', $propertyB))->assertNotFound();

        $this->actingAs($manager)
            ->get(route('user.owner.phase2.operations', $propertyA))->assertOk();

        PropertyStaffMembership::query()
            ->where('property_id', $propertyA->id)->where('user_id', $manager->id)
            ->update(['revoked_at' => now()]);

        $this->actingAs($manager)
            ->get(route('user.owner.phase2.operations', $propertyA))->assertNotFound();
    }
}
