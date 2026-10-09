<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyStaffMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffInvitationRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(Property $property, User $inviter, User $invitee): string
    {
        $token = Str::random(64);
        DB::table('property_staff_invitations')->insert([
            'property_id' => $property->id,
            'email' => $invitee->email,
            'role' => 'front_desk',
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function test_revoked_staff_cannot_redeem_another_pending_invitation(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $membership = PropertyStaffMembership::query()->create([
            'property_id' => $property->id,
            'user_id' => $staff->id,
            'role' => 'front_desk',
            'invited_by' => $owner->id,
            'accepted_at' => now(),
        ]);
        $token = $this->invitation($property, $owner, $staff);

        $this->actingAs($owner)->delete(route('user.owner.phase2.staff.revoke', [$property, $membership]))
            ->assertRedirect();

        $this->actingAs($staff)->get(route('user.owner.staff.accept', $token))
            ->assertNotFound();

        $this->assertNotNull($membership->fresh()->revoked_at);
    }

    public function test_pending_invitation_is_invalid_when_issuing_owner_loses_property(): void
    {
        $formerOwner = User::factory()->create(['email_verified_at' => now()]);
        $newOwner = User::factory()->create(['email_verified_at' => now()]);
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $formerOwner->id]);
        $token = $this->invitation($property, $formerOwner, $invitee);

        $property->update(['owner_id' => $newOwner->id]);

        $this->actingAs($invitee)->get(route('user.owner.staff.accept', $token))
            ->assertForbidden();

        $this->assertDatabaseCount('property_staff_memberships', 0);
    }
}
