<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\Property;
use App\Models\PropertyVerifiedClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedPropertyClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_verified_unexpired_claim_is_public_and_evidence_stays_private(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true, 'is_active' => true,
            'staff_role' => 'administrator', 'email_verified_at' => now(),
        ]);
        $property = Property::factory()->create([
            'is_published' => true, 'status' => 'active',
        ]);
        $reference = 'PRIVATE-STAFF-EVIDENCE-9876';
        $url = route('azari.admin.properties.claims.update', $property);

        $this->actingAs($admin)->post($url, [
            'claim_type' => 'address',
            'action' => 'verify',
            'evidence_reference' => $reference,
            'expires_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $claim = PropertyVerifiedClaim::query()
            ->where('property_id', $property->id)
            ->where('claim_type', 'address')->firstOrFail();

        $this->assertSame('verified', $claim->status);
        $this->assertCount(1, $property->fresh()->publicVerifiedClaims);
        $this->get(route('properties.show', $property))
            ->assertOk()
            ->assertSeeText('Address verified')
            ->assertDontSee($reference);

        $property->update(['formatted_address' => '10 New Market Road, Lagos']);
        $this->assertSame('revoked', $claim->fresh()->status);
        $this->assertCount(0, $property->fresh()->publicVerifiedClaims);
    }

    public function test_unprivileged_guest_cannot_assert_verified_property_claims(): void
    {
        $property = Property::factory()->create();
        $guest = User::factory()->create(['is_admin' => false, 'staff_role' => null]);
        $this->actingAs($guest)->post(route('azari.admin.properties.claims.update', $property), [
            'claim_type' => 'wifi_speed',
            'action' => 'verify',
            'evidence_reference' => 'FAKE-EVIDENCE',
            'expires_at' => now()->addMonth()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseCount('property_verified_claims', 0);
    }

    public function test_expired_claim_is_excluded_without_waiting_for_a_cleanup_task(): void
    {
        $property = Property::factory()->create();
        PropertyVerifiedClaim::query()->create([
            'property_id' => $property->id,
            'claim_type' => 'power_backup',
            'status' => 'verified',
            'evidence_reference' => 'PRIVATE-EVIDENCE',
            'verified_at' => now()->subMonth(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertCount(0, $property->fresh()->publicVerifiedClaims);
    }
}
