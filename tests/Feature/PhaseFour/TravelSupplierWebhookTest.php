<?php

namespace Tests\Feature\PhaseFour;

use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TravelSupplier;
use App\Models\User;
use App\Services\Travel\TravelRequestService;
use App\Services\Travel\TravelSupplierEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakeTravelSupplierAdapter;
use Tests\TestCase;

final class TravelSupplierWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('travel.webhooks_enabled', true);
        config()->set('travel.requests_enabled', true);
        config()->set('travel.supplier_adapters', ['sandbox' => FakeTravelSupplierAdapter::class]);
    }

    private function createTravel(string $kind = 'transfer'): array
    {
        $staff = User::factory()->create();
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $supplier = TravelSupplier::query()->create([
            'name' => 'Sandbox partner', 'kind' => $kind, 'status' => 'approved',
            'integration_key' => 'sandbox', 'approved_by' => $staff->id,
            'contract_verified_at' => now(), 'safety_verified_at' => now(),
        ]);
        $offer = TravelOffer::query()->create([
            'travel_supplier_id' => $supplier->id, 'kind' => $kind,
            'title' => 'Mock supplier rate', 'origin' => 'Lagos airport', 'destination' => 'Lekki',
            'timezone' => 'Africa/Lagos', 'max_party' => 4,
            'price_basis' => $kind === 'flight' ? 'per_person' : 'per_vehicle',
            'currency' => 'USD', 'base_minor' => 1200,
            'terms' => ['included' => 'Example', 'cancellation' => 'Example', 'disclosure' => 'Example'],
            'expires_at' => now()->addHours(2), 'published_at' => now()->subMinute(),
        ]);
        $request = app(TravelRequestService::class)->create($guest, $offer, [
            'idempotency_key' => (string) Str::uuid(), 'party_size' => 1,
            'data_share_consent' => true,
        ]);

        return [$guest, $supplier, $request];
    }

    private function signedPost(TravelSupplier $supplier, array $payload, bool $valid = true)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $valid
            ? 'travel-test-only-secret' : 'wrong-signing-secret');
        return $this->call('POST', route('travel.supplier.webhook', $supplier), [], [],
            [], ['CONTENT_TYPE' => 'application/json',
                'HTTP_X_RESAVAR_SUPPLIER_SIGNATURE' => $signature], $body);
    }

    public function test_signed_supplier_acknowledgement_is_deduped_and_only_changes_travel_request(): void
    {
        [$guest, $supplier, $travel] = $this->createTravel();
        $event = [
            'event_id' => 'provider-event-100',
            'event_type' => 'acknowledged',
            'travel_request_id' => $travel->id,
            'supplier_reference' => 'provider-ack-900',
        ];

        $this->signedPost($supplier, $event)->assertAccepted()->assertJsonPath('duplicate', false);
        $this->signedPost($supplier, $event)->assertAccepted()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('travel_supplier_webhook_events', 1);
        $this->assertSame(['processed' => 1, 'rejected' => 0],
            app(TravelSupplierEventProcessor::class)->process());
        $this->assertSame(['processed' => 0, 'rejected' => 0],
            app(TravelSupplierEventProcessor::class)->process());
        $this->assertSame('supplier_acknowledged', $travel->fresh()->status);
        $this->assertSame(1, $guest->fresh()->notifications()->count());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseHas('travel_supplier_webhook_events', [
            'external_event_id' => 'provider-event-100',
            'status' => 'processed', 'encrypted_body' => null,
        ]);
    }

    public function test_unsigned_events_and_same_id_different_payload_are_rejected(): void
    {
        [$guest, $supplier, $travel] = $this->createTravel();
        $event = [
            'event_id' => 'provider-event-200',
            'event_type' => 'declined',
            'travel_request_id' => $travel->id,
        ];
        $this->signedPost($supplier, $event, false)->assertForbidden();
        $this->assertDatabaseCount('travel_supplier_webhook_events', 0);

        $this->signedPost($supplier, $event)->assertAccepted();
        $event['event_type'] = 'disrupted';
        $this->signedPost($supplier, $event)->assertStatus(409);
        $this->assertDatabaseCount('travel_supplier_webhook_events', 1);
    }

    public function test_supplier_cannot_change_other_supplier_request(): void
    {
        [$guest, $supplier, $travel] = $this->createTravel();
        [$secondGuest, $secondSupplier, $secondTravel] = $this->createTravel();
        $event = [
            'event_id' => 'provider-event-300',
            'event_type' => 'declined',
            'travel_request_id' => $secondTravel->id,
        ];
        $this->signedPost($supplier, $event)->assertAccepted();
        $this->assertSame(['processed' => 0, 'rejected' => 1],
            app(TravelSupplierEventProcessor::class)->process());
        $this->assertSame('requested', $secondTravel->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_signed_webhook_does_not_resurrect_cancelled_request(): void
    {
        [$guest, $supplier, $travel] = $this->createTravel();
        app(TravelRequestService::class)->cancel($guest, $travel);
        $event = [
            'event_id' => 'provider-event-500',
            'event_type' => 'acknowledged',
            'travel_request_id' => $travel->id,
            'supplier_reference' => 'provider-ack-500',
        ];
        $this->signedPost($supplier, $event)->assertAccepted();
        $this->assertSame(['processed' => 0, 'rejected' => 1],
            app(TravelSupplierEventProcessor::class)->process());
        $this->assertSame('cancelled', $travel->fresh()->status);
        $this->assertSame(0, $guest->fresh()->notifications()->count());
    }

    public function test_signed_webhook_is_rejected_after_supplier_is_paused(): void
    {
        [$guest, $supplier, $travel] = $this->createTravel();
        $event = [
            'event_id' => 'provider-event-600', 'event_type' => 'acknowledged',
            'travel_request_id' => $travel->id,
            'supplier_reference' => 'provider-ack-600',
        ];
        $this->signedPost($supplier, $event)->assertAccepted();
        $supplier->update(['status' => 'paused']);
        $this->assertSame(['processed' => 0, 'rejected' => 1],
            app(TravelSupplierEventProcessor::class)->process());
        $this->assertSame('requested', $travel->fresh()->status);
    }

    public function test_flight_fare_increase_or_supplier_outage_fails_closed_without_payment(): void
    {
        config()->set('travel.test_fare_increase_minor', 100);
        $staff = User::factory()->create();
        $guest = User::factory()->create();
        $supplier = TravelSupplier::query()->create([
            'name' => 'Air sandbox', 'kind' => 'flight', 'status' => 'approved',
            'integration_key' => 'sandbox', 'approved_by' => $staff->id,
            'contract_verified_at' => now(), 'safety_verified_at' => now(),
        ]);
        $offer = TravelOffer::query()->create([
            'travel_supplier_id' => $supplier->id, 'kind' => 'flight',
            'title' => 'Sandbox flight', 'timezone' => 'UTC', 'max_party' => 1,
            'price_basis' => 'per_person', 'currency' => 'USD', 'base_minor' => 10000,
            'terms' => ['included' => 'Sample', 'cancellation' => 'Sample', 'disclosure' => 'No ticketing'],
            'expires_at' => now()->addHour(), 'published_at' => now(),
        ]);
        try {
            app(TravelRequestService::class)->create($guest, $offer,
                ['idempotency_key' => (string) Str::uuid(), 'party_size' => 1]);
            $this->fail('Changed fares must be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('offer_id', $e->errors());
        }
        config()->set('travel.test_fare_increase_minor', 0);
        config()->set('travel.test_supplier_unavailable', true);
        try {
            app(TravelRequestService::class)->create($guest, $offer,
                ['idempotency_key' => (string) Str::uuid(), 'party_size' => 1]);
            $this->fail('Supplier unavailability must be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('offer_id', $e->errors());
        }
        $this->assertDatabaseCount('travel_requests', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_declined_flight_enquiry_does_not_create_ticket_or_charge(): void
    {
        [$guest, $supplier, $travel] = $this->createTravel('flight');
        $event = [
            'event_id' => 'provider-event-400', 'event_type' => 'declined',
            'travel_request_id' => $travel->id,
        ];
        $this->signedPost($supplier, $event)->assertAccepted();
        app(TravelSupplierEventProcessor::class)->process();

        $this->assertSame('supplier_declined', $travel->fresh()->status);
        $this->assertNull($travel->fresh()->supplier_reference);
        $this->assertSame(1, $guest->fresh()->notifications()->count());
        $this->assertDatabaseCount('payments', 0);
    }
}
