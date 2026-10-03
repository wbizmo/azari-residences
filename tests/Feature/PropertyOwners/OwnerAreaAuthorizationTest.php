<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAreaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_public_listing_entry(): void
    {
        $this->get(route('public.list-property'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_can_open_property_centre(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->verifyIdentity($user);

        $this->actingAs($user)
            ->get(route('user.owner.dashboard'))
            ->assertOk();
    }

    public function test_customer_cannot_open_another_owners_listing(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->verifyIdentity($other);

        $listing = $owner->propertyListings()->create([
            'reference' => 'LIST-TEST-001',
            'listing_agreement_id' => $owner->listingAgreements()->create([
                'version' => 'test',
                'legal_name' => $owner->name,
                'agreement_text' => 'Test agreement',
                'signature_hash' => hash('sha256', 'test'),
                'signed_at' => now(),
            ])->id,
            'status' => 'submitted',
            'property_data' => ['name' => 'Private property'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($other)
            ->get(route('user.owner.listings.show', $listing))
            ->assertNotFound();
    }
    private function verifyIdentity(User $user): void
    {
        IdentityVerification::query()->create([
            'user_id' => $user->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => 'owner-test-'.$user->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }

}
