<?php

namespace Tests\Feature\PhaseFour;

use App\Models\Booking;
use App\Models\TravelExperienceSlot;
use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TravelSupplier;
use App\Models\TripItinerary;
use App\Models\User;
use App\Services\Travel\TravelRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TravelSupplierRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('travel.requests_enabled', true);
    }

    private function offer(string $kind = 'experience', bool $approved = true): TravelOffer
    {
        $operator = User::factory()->create();
        $supplier = TravelSupplier::query()->create([
            'kind' => $kind,
            'name' => 'Licensed sample partner',
            'status' => $approved ? 'approved' : 'pending',
            'support_email' => 'supplier@example.test',
            'contract_verified_at' => $approved ? now() : null,
            'safety_verified_at' => $approved ? now() : null,
            'approved_by' => $approved ? $operator->id : null,
        ]);

        return TravelOffer::query()->create([
            'travel_supplier_id' => $supplier->id,
            'kind' => $kind,
            'title' => 'Sample supplier availability enquiry',
            'origin' => 'Airport',
            'destination' => 'Hotel',
            'timezone' => 'Africa/Lagos',
            'max_party' => 5,
            'currency' => 'USD',
            'base_minor' => 1500,
            'tax_minor' => 100,
            'fee_minor' => 200,
            'deposit_minor' => $kind === 'car' ? 2000 : 0,
            'terms' => [
                'cancellation' => 'Cancellation subject to approval.',
                'included' => 'Base service.',
                'disclosure' => 'This is an enquiry, not a confirmed reservation.',
            ],
            'starts_at' => now()->addDays(2),
            'expires_at' => now()->addDay(),
            'published_at' => now()->subMinute(),
        ]);
    }

    public function test_feature_is_disabled_by_default_in_a_separate_request(): void
    {
        config()->set('travel.requests_enabled', false);
        $guest = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($guest)->get(route('user.travel.index'))->assertNotFound();
        $this->actingAs($guest)->post(route('user.travel.store'), [])->assertNotFound();
    }

    public function test_guest_request_is_idempotent_and_never_creates_a_paid_booking(): void
    {
        $offer = $this->offer('transfer');
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $itinerary = TripItinerary::query()->create(['user_id' => $guest->id, 'name' => 'Beach holiday']);
        $requestKey = (string) Str::uuid();
        $payload = [
            'offer_id' => $offer->id,
            'party_size' => 2,
            'idempotency_key' => $requestKey,
            'trip_itinerary_id' => $itinerary->id,
            'bags' => 2,
            'data_share_consent' => 1,
        ];

        $this->actingAs($guest)->post(route('user.travel.store'), $payload)->assertRedirect();
        $this->actingAs($guest)->post(route('user.travel.store'), $payload)->assertRedirect();

        $this->assertSame(1, TravelRequest::query()->where('user_id', $guest->id)->count());
        $request = TravelRequest::query()->firstOrFail();
        $this->assertSame('requested', $request->status);
        $this->assertSame(3600, (int) $request->quoted_total_minor);
        $this->assertSame($itinerary->id, $request->trip_itinerary_id);
        $this->assertNull($request->supplier_reference);
        $this->assertDatabaseCount('travel_request_events', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_final_experience_space_cannot_be_held_twice_and_expired_holds_release_capacity(): void
    {
        $offer = $this->offer();
        $slot = TravelExperienceSlot::query()->create([
            'travel_offer_id' => $offer->id,
            'starts_at' => now()->addHours(4),
            'capacity' => 2,
        ]);
        $guestA = User::factory()->create(['email_verified_at' => now()]);
        $guestB = User::factory()->create(['email_verified_at' => now()]);

        $first = app(TravelRequestService::class)->create($guestA, $offer, [
            'idempotency_key' => (string) Str::uuid(),
            'party_size' => 2,
            'slot_id' => $slot->id,
        ]);
        try {
            app(TravelRequestService::class)->create($guestB, $offer, [
                'idempotency_key' => (string) Str::uuid(),
                'party_size' => 1,
                'slot_id' => $slot->id,
            ]);
            $this->fail('Expected capacity rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slot_id', $e->errors());
        }

        $first->update(['expires_at' => now()->subSecond()]);
        $new = app(TravelRequestService::class)->create($guestB, $offer, [
            'idempotency_key' => (string) Str::uuid(),
            'party_size' => 1,
            'slot_id' => $slot->id,
        ]);
        $this->assertSame('requested', $new->status);
        $this->assertSame('expired', $first->fresh()->status);
    }

    public function test_idempotency_key_cannot_be_reused_with_different_party_size(): void
    {
        $offer = $this->offer('car');
        $guest = User::factory()->create();
        $key = (string) Str::uuid();
        $service = app(TravelRequestService::class);
        $service->create($guest, $offer, ['idempotency_key' => $key, 'party_size' => 1]);
        $this->expectException(ValidationException::class);
        $service->create($guest, $offer, ['idempotency_key' => $key, 'party_size' => 2]);
    }

    public function test_unapproved_supplier_and_foreign_itinerary_cannot_be_requested(): void
    {
        $offer = $this->offer('flight', false);
        $guest = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(TravelRequestService::class)->create($guest, $offer, [
            'idempotency_key' => (string) Str::uuid(), 'party_size' => 1,
        ]);
    }

    public function test_cancelling_travel_never_changes_the_linked_stay_and_cross_account_access_is_denied(): void
    {
        $offer = $this->offer('transfer');
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $booking = Booking::factory()->create(['user_id' => $guest->id, 'status' => 'confirmed']);
        $travel = app(TravelRequestService::class)->create($guest, $offer, [
            'idempotency_key' => (string) Str::uuid(),
            'party_size' => 1,
            'booking_id' => $booking->id,
        ]);

        $this->actingAs($stranger)->get(route('user.travel.show', $travel))->assertNotFound();
        $this->actingAs($stranger)->post(route('user.travel.cancel', $travel))->assertNotFound();

        $this->actingAs($guest)->post(route('user.travel.cancel', $travel))->assertRedirect();
        $this->assertSame('cancelled', $travel->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('travel_request_events', 2);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_unconfigured_flight_adapter_cannot_make_a_quote_requestable(): void
    {
        $offer = $this->offer('flight');
        $offer->supplier->update(['integration_key' => 'missing-provider']);
        config()->set('travel.supplier_adapters', ['missing-provider' => \stdClass::class]);

        $this->assertFalse($offer->fresh()->isRequestable());
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($guest)->get(route('user.travel.index', ['kind' => 'flight']))
            ->assertOk()->assertDontSee('Sample supplier availability enquiry');
    }

    public function test_pausing_supplier_or_unpublishing_offer_blocks_new_enquiries(): void
    {
        $offer = $this->offer('car');
        $guest = User::factory()->create();
        $offer->supplier->update(['status' => 'paused']);
        $this->assertFalse($offer->fresh()->isRequestable());
        $offer->supplier->update(['status' => 'approved']);
        $offer->update(['published_at' => null]);
        $this->assertFalse($offer->fresh()->isRequestable());
    }

    public function test_staff_review_requires_actual_acknowledgement_and_guest_consent(): void
    {
        $offer = $this->offer('transfer');
        $guest = User::factory()->create();
        $staff = User::factory()->create();
        $travel = app(TravelRequestService::class)->create($guest, $offer, [
            'idempotency_key' => (string) Str::uuid(), 'party_size' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(TravelRequestService::class)->review($travel, $staff, true, 'SUPPLIER-ACK-123');
    }
}
