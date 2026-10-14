<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\ListingAgreement;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Owners\ListingCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OwnerHotelOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        ListingAgreement::query()->create([
            'user_id' => $user->id,
            'version' => (string) \App\Models\SiteSetting::valueFor('owner_listing_agreement_version', '1.0'),
            'legal_name' => $user->name,
            'agreement_text' => 'Legally signed listing authorization',
            'signature_hash' => hash('sha256', 'owner'.$user->id),
            'signed_at' => now(),
        ]);
        return $user;
    }

    private function fullData(array $fields = []): array
    {
        return array_replace([
            'intent' => 'submit',
            'operator_type' => 'hotel_operator',
            'business_legal_name' => 'Cedar Hospitality Limited',
            'business_registration_number' => 'RC112233',
            'business_email' => 'operations@example.test',
            'business_phone' => '+2348000000000',
            'name' => 'Cedar Harbour Hotel',
            'location_id' => Location::factory()->create()->id,
            'room_type_id' => RoomType::factory()->create()->id,
            'formatted_address' => '12 Harbour Road, Lagos',
            'bedrooms' => 0,
            'bathrooms' => 1,
            'max_guests' => 2,
            'nightly_rate' => 45000,
            'currency' => (string) config('localization.default_currency', 'USD'),
            'amenities' => [\App\Models\Amenity::query()->create([
                'name' => 'Wi-Fi '.\Illuminate\Support\Str::random(5),
                'is_active' => true,
            ])->id],
            'short_description' => 'Comfortable hotel accommodation in Lagos.',
            'description' => str_repeat('Quiet, clean accommodation with reception and housekeeping. ', 2),
            'proof_of_control' => UploadedFile::fake()->create('hotel-lease.pdf', 80, 'application/pdf'),
        ], $fields);
    }

    public function test_owner_can_save_incomplete_private_draft_without_external_identity_provider(): void
    {
        Storage::fake('private');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('user.owner.listings.store'), [
            'intent' => 'draft',
            'operator_type' => 'individual',
            'name' => 'My Future Apartment',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $listing = PropertyListing::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('draft', $listing->status);
        $this->assertSame('individual', $listing->operator_type);
        $this->assertNull($listing->submitted_at);
        $this->assertNull($listing->cover_image);
        $this->assertFalse(app(ListingCompletenessService::class)->evaluate($listing)['publishable']);
    }

    public function test_hotel_submission_has_business_identity_and_private_control_proof(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $owner = $this->owner();
        $data = $this->fullData([
            'cover_image' => UploadedFile::fake()->image('hotel.jpg'),
        ]);

        $this->actingAs($owner)->post(route('user.owner.listings.store'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();

        $listing = PropertyListing::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('submitted', $listing->status);
        $this->assertSame('hotel_operator', $listing->operator_type);
        $this->assertSame('Cedar Hospitality Limited', $listing->business_legal_name);
        $this->assertSame('RC112233', $listing->business_registration_number);
        $this->assertCount(1, $listing->controlDocuments);
        $document = $listing->controlDocuments->first();
        $this->assertSame('pending', $document->status);
        Storage::disk('private')->assertExists($document->path);
        $this->assertStringStartsWith('owner-control/', $document->path);
        $this->assertNotContains($document->path, $listing->gallery ?? []);
    }

    public function test_hotel_cannot_submit_without_business_contact_and_proof(): void
    {
        Storage::fake('private');
        $owner = $this->owner();
        $data = $this->fullData();
        unset($data['business_legal_name'], $data['business_email'], $data['proof_of_control']);

        $this->actingAs($owner)->post(route('user.owner.listings.store'), $data)
            ->assertSessionHasErrors(['business_legal_name', 'business_email', 'proof_of_control']);
        $this->assertDatabaseCount('property_listings', 0);
    }

    public function test_submission_is_rejected_if_private_proof_is_oversized_or_wrong_media_type(): void
    {
        Storage::fake('private');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('user.owner.listings.store'), $this->fullData([
            'proof_of_control' => UploadedFile::fake()->create('control.exe', 200, 'application/octet-stream'),
        ]))->assertSessionHasErrors('proof_of_control');

        $this->actingAs($owner)->post(route('user.owner.listings.store'), $this->fullData([
            'proof_of_control' => UploadedFile::fake()->create('large.pdf', 12000, 'application/pdf'),
        ]))->assertSessionHasErrors('proof_of_control');
    }

    public function test_owner_cannot_edit_or_see_another_owners_private_proof(): void
    {
        Storage::fake('private');
        $owner = $this->owner();
        $stranger = $this->owner();

        $this->actingAs($owner)->post(route('user.owner.listings.store'), $this->fullData([
            'intent' => 'draft',
        ]))->assertSessionHasNoErrors();

        $listing = PropertyListing::query()->where('user_id', $owner->id)->firstOrFail();
        $this->actingAs($stranger)->get(route('user.owner.listings.edit', $listing))
            ->assertNotFound();
        $this->actingAs($stranger)->put(route('user.owner.listings.update', $listing), [
            'intent' => 'draft', 'name' => 'Tampered',
        ])->assertNotFound();
        $this->assertNotSame('Tampered', data_get($listing->fresh()->property_data, 'name'));
    }

    public function test_duplicate_active_listing_is_not_created_for_same_property_and_owner(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $owner = $this->owner();
        $data = $this->fullData([
            'cover_image' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $this->actingAs($owner)->post(route('user.owner.listings.store'), $data)->assertSessionHasNoErrors();

        $duplicate = $this->fullData([
            'name' => '  CEDAR HARBOUR HOTEL ',
            'cover_image' => UploadedFile::fake()->image('second.jpg'),
            'location_id' => $data['location_id'],
            'room_type_id' => $data['room_type_id'],
        ]);
        $this->actingAs($owner)->post(route('user.owner.listings.store'), $duplicate)
            ->assertSessionHasErrors('name');
        $this->assertSame(1, PropertyListing::query()->where('user_id', $owner->id)->count());
    }

    public function test_admin_can_request_corrections_and_owner_can_resubmit_without_new_listing(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $owner = $this->owner();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $data = $this->fullData([
            'cover_image' => UploadedFile::fake()->image('hotel.jpg'),
        ]);
        $this->actingAs($owner)->post(route('user.owner.listings.store'), $data)->assertSessionHasNoErrors();
        $listing = PropertyListing::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('azari.admin.owner-listings.corrections', $listing), [
            'reason' => 'Please correct the legal business contact and upload the updated lease document.',
        ])->assertRedirect();

        $this->assertSame('correction_requested', $listing->fresh()->status);
        $this->assertTrue($owner->fresh()->notifications()->exists());

        $this->actingAs($owner)->put(route('user.owner.listings.update', $listing), [
            'intent' => 'draft', 'business_email' => 'corrected@example.test',
        ])->assertSessionHasNoErrors();

        $this->assertSame('draft', $listing->fresh()->status);
        $this->assertSame(1, PropertyListing::query()->where('user_id', $owner->id)->count());
    }

    public function test_private_control_document_is_never_public_and_another_owner_cannot_download_it(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($owner)->post(route('user.owner.listings.store'), $this->fullData([
            'cover_image' => UploadedFile::fake()->image('front.jpg'),
        ]))->assertSessionHasNoErrors();

        $listing = PropertyListing::query()->where('user_id', $owner->id)->firstOrFail();
        $document = $listing->controlDocuments()->firstOrFail();

        Storage::disk('public')->assertMissing($document->path);
        $this->actingAs($other)->get(route('user.owner.listings.control-documents.download', [$listing, $document]))
            ->assertNotFound();
        $this->actingAs($owner)->get(route('user.owner.listings.control-documents.download', [$listing, $document]))
            ->assertOk()->assertHeader('Cache-Control', 'private, no-store');
    }

    public function test_approval_requires_manual_private_control_document_review_for_new_submissions(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $owner = $this->owner();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($owner)->post(route('user.owner.listings.store'), $this->fullData([
            'cover_image' => UploadedFile::fake()->image('hotel.jpg'),
        ]))->assertSessionHasNoErrors();
        $listing = PropertyListing::query()->where('user_id', $owner->id)->firstOrFail();

        $review = $listing->controlDocuments()->firstOrFail();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('azari.admin.owner-listings.control-documents.review', [$listing, $review]), [
            'status' => 'verified',
            'review_note' => 'Reviewed original authorization and matching registration evidence.',
        ])->assertRedirect();
        $this->assertSame('verified', $review->fresh()->status);

        $otherOwner = $this->owner();
        $other = PropertyListing::query()->create([
            'user_id' => $otherOwner->id,
            'listing_agreement_id' => $otherOwner->listingAgreements()->first()->id,
            'status' => 'draft', 'property_data' => [],
        ]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('azari.admin.owner-listings.control-documents.review', [$other, $review]), [
            'status' => 'rejected', 'review_note' => 'This is someone else’s document.',
        ])->assertNotFound();
    }
}
