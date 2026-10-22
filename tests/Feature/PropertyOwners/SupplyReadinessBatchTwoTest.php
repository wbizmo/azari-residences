<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\CancellationPolicy;
use App\Models\PaymentPolicy;
use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use App\Models\PropertyVerifiedClaim;
use App\Models\User;
use App\Services\Owners\PropertyPublicationService;
use App\Services\Owners\SupplyReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SupplyReadinessBatchTwoTest extends TestCase
{
    use RefreshDatabase;

    private function buildReviewableProperty(): Property
    {
        Storage::fake('public');
        Storage::fake('private');
        $property = Property::factory()->create([
            'status' => 'draft', 'is_published' => false, 'is_featured' => false,
            'cover_image' => 'properties/covers/batch02.jpg',
            'formatted_address' => '4 Marina Avenue, Lagos',
            'description' => str_repeat('Quality serviced accommodation with reliable facilities. ', 2),
            'tax_rate' => 7.5, 'currency' => 'NGN',
        ]);
        Storage::disk('public')->put('properties/covers/batch02.jpg', 'sample jpg fixture');
        $property->photoModerations()->create([
            'path' => 'properties/covers/batch02.jpg',
            'path_hash' => PropertyPhotoModeration::pathHash('properties/covers/batch02.jpg'),
            'status' => 'approved', 'alt_text' => 'Exterior entrance of this accommodation',
            'attribution' => 'Authorized property photographer',
            'reviewed_at' => now(),
        ]);
        $amenity = Amenity::query()->create(['name' => 'Wi-Fi batch 02', 'is_active' => true]);
        $property->amenities()->sync([$amenity->id]);
        $type = $property->accommodationTypes()->firstOrFail();
        $type->update(['is_active' => true, 'is_published' => true, 'total_inventory' => 2, 'base_rate' => 50000, 'currency' => 'NGN']);
        $payment = PaymentPolicy::query()->create([
            'property_id' => $property->id, 'name' => 'Pay upfront',
            'payment_type' => 'full', 'is_active' => true,
        ]);
        $cancellation = CancellationPolicy::query()->create([
            'property_id' => $property->id, 'name' => 'Standard cancellation',
            'policy_type' => 'non_refundable', 'is_active' => true,
        ]);
        $type->ratePlans()->firstOrFail()->update([
            'is_active' => true, 'is_public' => true,
            'cancellation_policy_id' => $cancellation->id,
            'payment_policy_id' => $payment->id,
        ]);
        return $property;
    }

    public function test_dashboard_is_restricted_to_administrators_and_never_claims_draft_is_bookable(): void
    {
        $property = $this->buildReviewableProperty();
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $url = route('azari.admin.supply-readiness.index');
        $this->actingAs($guest)->get($url)->assertStatus(404);
        $this->actingAs($admin)->get($url)
            ->assertOk()->assertSee($property->name)
            ->assertSee('Not publicly available')
            ->assertDontSee('Verified owner-control document missing or stale');
        $this->actingAs($admin)->get($url.'?check_in=2000-01-01&check_out=2000-01-02')
            ->assertSessionHasNoErrors()->assertStatus(302)->assertRedirect();
    }

    public function test_only_independently_reviewed_published_and_quoted_property_is_date_bookable(): void
    {
        $property = $this->buildReviewableProperty();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $checkIn = CarbonImmutable::now()->addDays(3)->startOfDay();
        $checkOut = $checkIn->addDay();
        $service = app(SupplyReadinessService::class);

        $before = $service->inspect($property, $checkIn, $checkOut);
        $this->assertFalse($before['ready']);
        $this->assertSame('draft', $before['state']);

        $publishing = app(PropertyPublicationService::class);
        $publishing->review($property, $admin,
            'Verified current property rights, photos, pricing and cancellation terms.',
            ['identity' => true, 'property' => true, 'terms' => true]);
        $reviewed = $service->inspect($property->fresh(), $checkIn, $checkOut);
        $this->assertSame('reviewed', $reviewed['state']);
        $this->assertFalse($reviewed['ready']);

        $publishing->publish($property, $admin);
        $ready = $service->inspect($property->fresh(), $checkIn, $checkOut);
        $this->assertTrue($ready['ready']);
        $this->assertSame('bookable', $ready['state']);
        $this->assertGreaterThan(0, $ready['quote']['total']);

        $property->fresh()->accommodationTypes()->firstOrFail()
            ->update(['total_inventory' => 0]);
        $stopped = $service->inspect($property->fresh(), $checkIn, $checkOut);
        $this->assertFalse($stopped['ready']);
        $this->assertSame('published_not_bookable', $stopped['state']);
    }

    public function test_owner_transfer_revokes_claims_and_unpublishes_without_erasing_booking_history(): void
    {
        $property = $this->buildReviewableProperty();
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $publishing = app(PropertyPublicationService::class);
        $publishing->review($property, $admin, 'Verified complete property claims and imagery before publication.',
            ['identity' => true, 'property' => true, 'terms' => true]);
        $publishing->publish($property, $admin);

        foreach (['address', 'wifi_speed'] as $kind) {
            PropertyVerifiedClaim::query()->create([
                'property_id' => $property->id, 'claim_type' => $kind, 'status' => 'verified',
                'evidence_reference' => 'Reviewed internal case record',
                'verified_by' => $admin->id, 'verified_at' => now(),
                'expires_at' => now()->addMonth(),
            ]);
        }

        $newOwner = User::factory()->create();
        $property->update(['owner_id' => $newOwner->id]);
        $this->assertFalse((bool) $property->fresh()->is_published);
        $this->assertSame('draft', $property->fresh()->status);
        $this->assertSame(2, PropertyVerifiedClaim::query()
            ->where('property_id', $property->id)->where('status', 'revoked')->count());
        $this->assertDatabaseHas('property_publication_reviews', [
            'property_id' => $property->id, 'status' => 'unpublished',
        ]);
        $this->assertTrue(AuditLog::query()->where('action',
            'property.claims_revoked_on_change')->where('subject_id', $property->id)->exists());
    }

    public function test_private_weekly_supply_report_has_no_guest_data_or_booking_side_effects(): void
    {
        $this->buildReviewableProperty();
        $this->artisan('resavar:report-supply-readiness')->assertExitCode(0);
        Storage::disk('private')->assertExists('reports/accommodation-supply/'.now()->format('Y-m-d').'.json');
        $document = Storage::disk('private')->get('reports/accommodation-supply/'.now()->format('Y-m-d').'.json');
        $report = json_decode($document, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['properties']['total']);
        $this->assertSame(0, $report['properties']['bookable']);
        $this->assertArrayHasKey('owner_submissions', $report);
        $this->assertDatabaseCount('payments', 0);
    }
}
