<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\IdentityVerification;
use App\Models\ListingAgreement;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminPropertyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_active' => true]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Garden Harbour Hotel',
            'location_id' => Location::factory()->create()->id,
            'room_type_id' => RoomType::factory()->create()->id,
            'formatted_address' => '12 Harbour Road, Lagos',
            'bedrooms' => 0,
            'bathrooms' => 1,
            'max_guests' => 2,
            'nightly_rate' => 20000,
            'currency' => (string) config('localization.default_currency', 'USD'),
            'description' => str_repeat('A carefully managed stay with reliable guest support. ', 2),
            'owner_id' => '',
        ], $overrides);
    }

    private function verifiedOwner(): User
    {
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        IdentityVerification::query()->create([
            'user_id' => $owner->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => 'test-owner-'.$owner->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
        ListingAgreement::query()->create([
            'user_id' => $owner->id,
            'version' => (string) \App\Models\SiteSetting::valueFor('owner_listing_agreement_version', '1.0'),
            'legal_name' => $owner->name,
            'agreement_text' => 'Signed Resavar owner listing agreement.',
            'signature_hash' => hash('sha256', 'owner-'.$owner->id),
            'signed_at' => now(),
        ]);

        return $owner;
    }

    public function test_admin_can_create_first_party_draft_with_default_unit_and_rate(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('azari.admin.properties.store'), $this->data())
            ->assertSessionHasNoErrors()->assertRedirect();

        $property = Property::query()->where('name', 'Garden Harbour Hotel')->firstOrFail();
        $this->assertNull($property->owner_id);
        $this->assertSame('azari', $property->ownership_type);
        $this->assertFalse((bool) $property->is_published);
        $this->assertCount(1, $property->accommodationTypes);
        $this->assertSame(1, (int) $property->accommodationTypes->first()->total_inventory);
        $this->assertTrue($property->accommodationTypes->first()->ratePlans()->exists());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'property.created_by_admin',
            'subject_id' => $property->id,
        ]);
    }

    public function test_admin_assigns_verified_owner_with_current_agreement_but_cannot_auto_publish(): void
    {
        $admin = $this->admin();
        $owner = $this->verifiedOwner();
        $payload = $this->data([
            'name' => 'Owners New Villa',
            'owner_id' => $owner->id,
            'owner_share_percentage' => '87.50',
            'is_published' => '1',
            'is_featured' => '1',
        ]);

        $this->actingAs($admin)->post(route('azari.admin.properties.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $property = Property::query()->where('name', 'Owners New Villa')->firstOrFail();
        $this->assertSame($owner->id, (int) $property->owner_id);
        $this->assertSame('third_party', $property->ownership_type);
        $this->assertEqualsWithDelta(87.5, (float) $property->owner_share_percentage, 0.001);
        $this->assertFalse((bool) $property->is_published);
        $this->assertFalse((bool) $property->is_featured);

        $other = User::factory()->create();
        $this->actingAs($other)
            ->get(route('user.owner.commercial.edit', $property))
            ->assertNotFound();

        $this->actingAs($owner)->get(route('user.owner.commercial.edit', $property))
            ->assertOk();
    }

    public function test_admin_cannot_assign_unverified_unsignened_or_staff_owner(): void
    {
        $admin = $this->admin();
        $unverified = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($admin)
            ->post(route('azari.admin.properties.store'), $this->data([
                'name' => 'Not Eligible', 'owner_id' => $unverified->id,
            ]))->assertSessionHasErrors('owner_id');
        $this->assertDatabaseMissing('properties', ['name' => 'Not Eligible']);

        $staff = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($admin)
            ->post(route('azari.admin.properties.store'), $this->data([
                'name' => 'Staff Cannot Own', 'owner_id' => $staff->id,
            ]))->assertSessionHasErrors('owner_id');
        $this->assertDatabaseMissing('properties', ['name' => 'Staff Cannot Own']);
    }

    public function test_owner_assigned_draft_cannot_publish_before_media_review(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $owner = $this->verifiedOwner();
        $this->actingAs($admin)->post(route('azari.admin.properties.store'),
            $this->data([
                'name' => 'Photo Moderation Home',
                'owner_id' => $owner->id,
                'cover_image' => UploadedFile::fake()->image('front.jpg'),
            ]))->assertSessionHasNoErrors();

        $property = Property::query()->where('name', 'Photo Moderation Home')->firstOrFail();
        $this->assertSame('pending', $property->photoModerations()->firstOrFail()->status);
        $this->assertCount(0, $property->publicPhotoPaths());
        $this->assertFalse((bool) $property->is_published);

        $this->actingAs($admin)
            ->put(route('azari.admin.properties.update', $property),
                $this->data([
                    'name' => 'Photo Moderation Home',
                    'owner_id' => $owner->id,
                    'slug' => $property->slug,
                    'is_published' => '1',
                    'is_featured' => '1',
                ]))->assertSessionHasErrors('is_published');
        $this->assertFalse((bool) $property->fresh()->is_published);
    }

    public function test_duplicate_normalized_slug_rejected_before_creation(): void
    {
        $admin = $this->admin();
        Property::factory()->create(['slug' => 'reserved-unique-stay']);
        $before = Property::query()->count();

        $this->actingAs($admin)->post(route('azari.admin.properties.store'),
            $this->data(['name' => 'Collision', 'slug' => 'Reserved Unique Stay']))
            ->assertSessionHasErrors('slug');

        $this->assertSame($before, Property::query()->count());
    }

    public function test_existing_bookings_prevent_owner_transfer_and_commission_rewrite(): void
    {
        $admin = $this->admin();
        $ownerA = $this->verifiedOwner();
        $ownerB = $this->verifiedOwner();
        $property = Property::factory()->create([
            'owner_id' => $ownerA->id,
            'is_published' => false,
        ]);
        \App\Models\Booking::factory()->create(['property_id' => $property->id]);

        $this->actingAs($admin)->put(route('azari.admin.properties.update', $property),
            $this->data([
                'owner_id' => $ownerB->id,
                'name' => $property->name,
                'slug' => $property->slug,
            ]))->assertSessionHasErrors('owner_id');

        $this->assertSame($ownerA->id, (int) $property->fresh()->owner_id);
    }

    public function test_failed_update_never_deletes_previous_cover_photo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Storage::disk('public')->put('properties/covers/previous.jpg', 'old-photo-content');
        $property = Property::factory()->create([
            'cover_image' => 'properties/covers/previous.jpg',
        ]);

        $this->actingAs($admin)->put(route('azari.admin.properties.update', $property),
            $this->data([
                'name' => $property->name,
                'slug' => $property->slug,
                'owner_id' => User::factory()->create()->id,
                'cover_image' => UploadedFile::fake()->image('new.jpg'),
            ]))->assertSessionHasErrors('owner_id');

        Storage::disk('public')->assertExists('properties/covers/previous.jpg');
        $this->assertSame('properties/covers/previous.jpg', $property->fresh()->cover_image);
    }
}
