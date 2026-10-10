<?php

namespace Tests\Feature\PhaseTwo;

use App\Http\Controllers\Admin\OwnerMarketplaceController;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Owners\ListingCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OwnerListingMediaApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function listingAndReviewer(): array
    {
        Storage::fake('public');
        Storage::disk('public')->put('owner-listings/covers/review.jpg', 'test-image');
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $owner = User::factory()->create();
        $source = Property::factory()->create();

        $listing = PropertyListing::query()->create([
            'reference' => 'LST-MEDIA-1',
            'user_id' => $owner->id,
            'listing_agreement_id' => $owner->listingAgreements()->create([
                'version' => 'test',
                'legal_name' => $owner->name,
                'agreement_text' => 'Test listing agreement',
                'signature_hash' => hash('sha256', 'listing-media-test'),
                'signed_at' => now(),
            ])->id,
            'status' => 'submitted',
            'property_data' => array_merge($source->fresh()->only($source->getFillable()), [
                'code' => 'MEDIA-'.$source->id,
                'slug' => 'media-approval-'.$source->id,
            ]),
            'cover_image' => 'owner-listings/covers/review.jpg',
            'gallery' => [],
            'amenity_ids' => [],
        ]);

        return [$listing, $admin];
    }

    private function requestFor(User $reviewer, array $fields): Request
    {
        $this->actingAs($reviewer);
        $request = Request::create('/owner-listing-approval', 'POST', $fields);
        $request->setUserResolver(static fn () => $reviewer);
        $this->app->instance('request', $request);
        return $request;
    }

    public function test_media_review_attestation_is_required_even_for_draft_publication(): void
    {
        [$listing, $admin] = $this->listingAndReviewer();
        $request = $this->requestFor($admin, ['publish_now' => false]);
        $service = Mockery::mock(ListingCompletenessService::class);
        $service->shouldNotReceive('sync');

        try {
            app(OwnerMarketplaceController::class)->approveListing($request, $listing, $service);
            $this->fail('The approval must be rejected before any property is created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('media_reviewed', $exception->errors());
        }

        $this->assertSame('submitted', $listing->fresh()->status);
    }

    public function test_repeated_admin_approval_cannot_create_a_second_property(): void
    {
        [$listing, $admin] = $this->listingAndReviewer();
        $request = $this->requestFor($admin, [
            'media_reviewed' => '1',
            'media_review_note' => 'All submitted property photographs were checked.',
            'photo_alt' => [hash('sha256', 'owner-listings/covers/review.jpg') =>
                'Exterior view of the submitted property'],
            'photo_attribution' => [hash('sha256', 'owner-listings/covers/review.jpg') =>
                'Owner supplied imagery'],
            'publish_now' => '1',
        ]);

        $service = Mockery::mock(ListingCompletenessService::class);
        $service->shouldReceive('sync')->once()->andReturn([
            'publishable' => true, 'blockers' => [],
        ]);

        $before = Property::query()->count();
        app(OwnerMarketplaceController::class)->approveListing($request, $listing, $service);
        $this->assertSame($before + 1, Property::query()->count());
        $this->assertNotNull($listing->fresh()->media_reviewed_at);
        $this->assertSame($admin->id, (int) $listing->fresh()->media_reviewed_by);

        try {
            app(OwnerMarketplaceController::class)->approveListing($request, $listing, $service);
            $this->fail('A repeated approval must not create another property.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame($before + 1, Property::query()->count());
    }
}
