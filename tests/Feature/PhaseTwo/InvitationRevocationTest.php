<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyStaffMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_cannot_be_redeemed_after_inviter_loses_permission(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $invitee = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        $membership = PropertyStaffMembership::query()->create([
            'property_id' => $property->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'accepted_at' => now(),
        ]);

        $token = Str::random(64);
        DB::table('property_staff_invitations')->insert([
            'property_id' => $property->id,
            'email' => $invitee->email,
            'role' => 'front_desk',
            'token_hash' => hash('sha256', $token),
            'invited_by' => $manager->id,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $membership->update(['revoked_at' => now()]);
        $this->actingAs($invitee)
            ->get(route('user.owner.staff.accept', ['token' => $token]))
            ->assertForbidden();

        $this->assertDatabaseMissing('property_staff_memberships', [
            'property_id' => $property->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_revoking_staff_clears_unused_invites_to_the_same_email(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $membership = PropertyStaffMembership::query()->create([
            'property_id' => $property->id,
            'user_id' => $staff->id,
            'role' => 'front_desk',
            'accepted_at' => now(),
        ]);
        DB::table('property_staff_invitations')->insert([
            'property_id' => $property->id,
            'email' => $staff->email,
            'role' => 'manager',
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by' => $owner->id,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete(route('user.owner.phase2.staff.revoke', [$property, $membership]))
            ->assertRedirect();

        $this->assertNotNull($membership->fresh()->revoked_at);
        $this->assertDatabaseMissing('property_staff_invitations', [
            'property_id' => $property->id,
            'email' => $staff->email,
            'accepted_at' => null,
        ]);
    }
}
