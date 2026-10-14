<?php
namespace Tests\Feature\PropertyOwners;

use App\Models\Amenity;
use App\Models\CancellationPolicy;
use App\Models\PaymentPolicy;
use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PropertyPublicationReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_active' => true]);
    }

    private function eligibleProperty(): Property
    {
        Storage::fake('public');
        $property = Property::factory()->create([
            'status' => 'draft', 'is_published' => false, 'is_featured' => false,
            'formatted_address' => '12 Market Road, Lagos',
            'description' => str_repeat('Safe serviced accommodation with reliable facilities. ', 2),
            'tax_rate' => 7.5,
            'cover_image' => 'properties/covers/valid.jpg',
        ]);
        Storage::disk('public')->put('properties/covers/valid.jpg', 'image fixture');
        PropertyPhotoModeration::query()->create([
            'property_id' => $property->id, 'path' => 'properties/covers/valid.jpg',
            'path_hash' => PropertyPhotoModeration::pathHash('properties/covers/valid.jpg'),
            'status' => 'approved', 'alt_text' => 'Exterior of the serviced accommodation',
            'attribution' => 'Property copyright holder', 'reviewed_by' => $this->admin()->id,
            'reviewed_at' => now(),
        ]);
        $amenity = Amenity::query()->create(['name' => 'Wifi review', 'is_active' => true]);
        $property->amenities()->sync([$amenity->id]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['is_active' => true, 'is_published' => true, 'total_inventory' => 3, 'base_rate' => 40000]);
        $payment = PaymentPolicy::query()->create([
            'property_id' => $property->id, 'name' => 'Full payment',
            'payment_type' => 'full', 'is_active' => true,
        ]);
        $cancellation = CancellationPolicy::query()->create([
            'property_id' => $property->id, 'name' => 'No refund after arrival',
            'policy_type' => 'non_refundable', 'is_active' => true,
        ]);
        $type->ratePlans()->firstOrFail()->update([
            'is_active' => true, 'is_public' => true,
            'payment_policy_id' => $payment->id, 'cancellation_policy_id' => $cancellation->id,
        ]);
        return $property;
    }

    private function review(Property $property): void
    {
        $this->post(route('azari.admin.properties.publication.review', $property), [
            'review_note' => 'Staff reviewed owner rights, address, photos, prices and policies.',
            'identity_checked' => '1', 'property_checked' => '1', 'terms_checked' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_review_then_publish_preserves_ids_and_audits_moderator(): void
    {
        $admin = $this->admin();
        $property = $this->eligibleProperty();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->review($property);
        $this->assertFalse((bool) $property->fresh()->is_published);
        $this->assertDatabaseHas('property_publication_reviews', [
            'property_id' => $property->id, 'status' => 'approved_draft', 'reviewed_by' => $admin->id,
        ]);
        $this->post(route('azari.admin.properties.publication.publish', $property))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue((bool) $property->fresh()->is_published);
        $this->assertSame($property->id, $property->fresh()->id);
        $this->assertSame(2, $property->publicationReviews()->count());
    }

    public function test_rejected_photo_or_missing_rate_policy_blocks_review(): void
    {
        $property = $this->eligibleProperty();
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $property->photoModerations()->firstOrFail()->update(['status' => 'rejected']);
        $this->post(route('azari.admin.properties.publication.review', $property), [
            'review_note' => 'All essential property evidence still requires verification.',
            'identity_checked' => '1', 'property_checked' => '1', 'terms_checked' => '1',
        ])->assertSessionHasErrors('is_published');
        $property->photoModerations()->firstOrFail()->update(['status' => 'approved']);
        $property->accommodationTypes()->firstOrFail()->ratePlans()->firstOrFail()->update(['payment_policy_id' => null]);
        $this->post(route('azari.admin.properties.publication.review', $property), [
            'review_note' => 'The payment terms for the sellable rate are missing.',
            'identity_checked' => '1', 'property_checked' => '1', 'terms_checked' => '1',
        ])->assertSessionHasErrors('is_published');
        $this->assertFalse((bool) $property->fresh()->is_published);
    }

    public function test_old_inventory_endpoint_cannot_bypass_publication(): void
    {
        $property = $this->eligibleProperty();
        $this->actingAs($this->admin())->put(
            route('azari.admin.inventory.properties.update', $property),
            ['is_published' => '1']
        )->assertStatus(410);
        $this->assertFalse((bool) $property->fresh()->is_published);
    }

    public function test_moderation_revocation_unpublishes_live_listing_without_deleting_bookings(): void
    {
        $property = $this->eligibleProperty();
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $this->review($property);
        $this->post(route('azari.admin.properties.publication.publish', $property))
            ->assertSessionHasNoErrors();
        $photo = $property->photoModerations()->firstOrFail();
        $this->patch(route('azari.admin.properties.photos.update', [$property, $photo]), [
            'status' => 'rejected', 'review_note' => 'Rights holder withdrew photographic permission.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse((bool) $property->fresh()->is_published);
        $this->assertSame($property->id, $property->fresh()->id);
        $this->assertSame('unpublished', $property->publicationReviews()->latest('id')->first()->status);
    }

    public function test_unpublishing_requires_new_quality_review_and_duplicate_review_is_rejected(): void
    {
        $property = $this->eligibleProperty();
        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()]);
        $this->review($property);
        $this->post(route('azari.admin.properties.publication.review', $property), [
            'review_note' => 'A second moderator tried to reapprove the same draft.',
            'identity_checked' => '1', 'property_checked' => '1', 'terms_checked' => '1',
        ])->assertSessionHasErrors('is_published');
        $this->post(route('azari.admin.properties.publication.publish', $property))
            ->assertSessionHasNoErrors();
        $this->post(route('azari.admin.properties.publication.unpublish', $property), [
            'reason' => 'Quality hold pending updated rights verification.',
        ])->assertSessionHasNoErrors();
        $this->post(route('azari.admin.properties.publication.publish', $property))
            ->assertSessionHasErrors('is_published');
    }
}
