<?php

namespace Tests\Feature\PhaseFour;

use App\Models\Booking;
use App\Models\TravelExperienceSlot;
use App\Models\TravelFulfillment;
use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TravelSupplier;
use App\Models\User;
use App\Services\Travel\TravelFulfillmentService;
use App\Services\Travel\TravelRequestService;
use App\Services\Travel\TravelVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeConfirmingTravelAdapter;
use Tests\Support\FakeTravelPaymentVerifier;
use Tests\TestCase;

final class TravelFulfillmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('travel.requests_enabled', true);
        config()->set('travel.fulfillment_enabled', true);
        config()->set('travel.payment_verifier', FakeTravelPaymentVerifier::class);
        config()->set('travel.supplier_adapters', ['sandbox' => FakeConfirmingTravelAdapter::class]);
    }

    private function fixture(string $kind = 'experience'): array
    {
        $staff = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['email_verified_at' => now()]);
        $supplier = TravelSupplier::query()->create([
            'kind' => $kind,
            'name' => 'Sandbox approved provider',
            'status' => 'approved',
            'integration_key' => 'sandbox',
            'approved_by' => $staff->id,
            'contract_verified_at' => now(),
            'safety_verified_at' => now(),
        ]);
        $offer = TravelOffer::query()->create([
            'travel_supplier_id' => $supplier->id,
            'kind' => $kind,
            'title' => 'Verified sandbox example',
            'origin' => 'Airport',
            'destination' => 'Hotel',
            'timezone' => 'Africa/Lagos',
            'max_party' => 2,
            'price_basis' => in_array($kind, ['car', 'transfer']) ? 'per_vehicle' : 'per_person',
            'currency' => 'USD',
            'base_minor' => 1000,
            'tax_minor' => 100,
            'fee_minor' => 100,
            'terms' => ['included' => 'Included', 'cancellation' => 'Provider terms', 'disclosure' => 'Sandbox'],
            'published_at' => now()->subMinute(),
            'expires_at' => now()->addHours(2),
        ]);
        $slot = $kind === 'experience'
            ? TravelExperienceSlot::query()->create([
                'travel_offer_id' => $offer->id,
                'starts_at' => now()->addDay(),
                'capacity' => 2,
            ])
            : null;
        $booking = Booking::factory()->create(['user_id' => $guest->id, 'status' => 'confirmed']);
        $travel = app(TravelRequestService::class)->create($guest, $offer, [
            'idempotency_key' => (string) Str::uuid(),
            'party_size' => 1,
            'slot_id' => $slot?->id,
            'booking_id' => $booking->id,
            'data_share_consent' => true,
        ]);
        // Simulates a previously verified supplier-signed webhook, NOT a
        // staff/self-attested reservation.
        $travel->update([
            'status' => 'supplier_acknowledged',
            'supplier_reference' => 'sandbox-ack-'.$travel->id,
            'supplier_acknowledged_at' => now(),
        ]);
        config()->set('travel.test_verified_minor', 1200);
        return [$staff, $guest, $supplier, $travel, $booking];
    }

    public function test_an_experience_voucher_requires_independent_payment_and_provider_confirmation(): void
    {
        [$staff, $guest, $supplier, $travel, $booking] = $this->fixture();
        $f = TravelFulfillment::query()->create([
            'travel_request_id' => $travel->id, 'travel_supplier_id' => $supplier->id,
            'status' => 'awaiting_verification', 'currency' => 'USD', 'amount_minor' => 1200,
        ]);
        $this->expectException(ValidationException::class);
        app(TravelVoucherService::class)->issue($f);
    }

    public function test_verified_payment_is_idempotent_and_supplier_confirmation_issues_single_use_voucher(): void
    {
        [$staff, $guest, $supplier, $travel, $booking] = $this->fixture();
        $fulfillmentService = app(TravelFulfillmentService::class);
        $first = $fulfillmentService->recordVerifiedCapture($travel, 'sandbox-paid-0001');
        $second = $fulfillmentService->recordVerifiedCapture($travel, 'sandbox-paid-0001');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('payment_verified', $first->status);
        $this->assertDatabaseCount('travel_financial_events', 1);

        $confirmed = $fulfillmentService->requestSupplierReservation($first);
        $this->assertSame('confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->provider_confirmation);
        $this->assertSame('confirmed', $booking->fresh()->status);

        $voucher = app(TravelVoucherService::class)->issue($confirmed);
        $again = app(TravelVoucherService::class)->issue($confirmed);
        $this->assertSame($voucher->id, $again->id);
        $this->assertDatabaseCount('travel_vouchers', 1);
        $token = app(TravelVoucherService::class)->displayToken($voucher, $guest);
        $this->assertMatchesRegularExpression('/^RVX-[A-F0-9]{40}$/', $token);
        $this->assertSame(hash('sha256', $token), $voucher->token_hash);

        $qr = $this->actingAs($guest)->get(route('user.travel.voucher.qr', $voucher));
        $qr->assertOk();
        $this->assertStringContainsString('no-store', (string) $qr->headers->get('Cache-Control'));
        $foreign = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($foreign)->get(route('user.travel.voucher.qr', $voucher))->assertNotFound();

        $redeemed = app(TravelVoucherService::class)->redeem($staff, $token, $supplier->id);
        $this->assertSame('redeemed', $redeemed->status);
        $this->assertNotNull($redeemed->redeemed_at);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('refunds', 0);

        try {
            app(TravelVoucherService::class)->redeem($staff, $token, $supplier->id);
            $this->fail('Copied voucher was redeemed twice');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('voucher', $e->errors());
        }
    }

    public function test_wrong_supplier_and_wrong_payment_amount_fail_closed(): void
    {
        [$staff, $guest, $supplier, $travel] = $this->fixture();
        config()->set('travel.test_verified_minor', 1199);
        try {
            app(TravelFulfillmentService::class)->recordVerifiedCapture($travel, 'sandbox-paid-0002');
            $this->fail('Underpayment should not create a travel order');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }
        $this->assertDatabaseCount('travel_fulfillments', 0);

        config()->set('travel.test_verified_minor', 1200);
        $fulfillment = app(TravelFulfillmentService::class)->recordVerifiedCapture($travel, 'sandbox-paid-0002');
        $confirmed = app(TravelFulfillmentService::class)->requestSupplierReservation($fulfillment);
        $voucher = app(TravelVoucherService::class)->issue($confirmed);
        $token = app(TravelVoucherService::class)->displayToken($voucher, $guest);

        try {
            app(TravelVoucherService::class)->redeem($staff, $token, $supplier->id + 42);
            $this->fail('Cross-supplier redemption should be denied');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('voucher', $e->errors());
        }
        $this->assertSame('issued', $voucher->fresh()->status);
    }

    public function test_signed_supplier_cancellation_revokes_voucher_and_flags_refund_review_without_refunding_stay(): void
    {
        [$staff, $guest, $supplier, $travel, $booking] = $this->fixture();
        $fulfillment = app(TravelFulfillmentService::class)->recordVerifiedCapture($travel, 'sandbox-paid-0005');
        $confirmed = app(TravelFulfillmentService::class)->requestSupplierReservation($fulfillment);
        $voucher = $confirmed->voucher()->firstOrFail();
        $this->assertSame('issued', $voucher->status);
        config()->set('travel.webhooks_enabled', true);
        $body = json_encode([
            'event_id' => 'sandbox-cancelled-500',
            'event_type' => 'cancelled',
            'travel_request_id' => $travel->id,
        ], JSON_THROW_ON_ERROR);
        $this->call('POST', route('travel.supplier.webhook', $supplier), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TEST_SIGNED' => 'local-test-only',
        ], $body)->assertAccepted();
        $this->assertSame(['processed' => 1, 'rejected' => 0],
            app(\App\Services\Travel\TravelSupplierEventProcessor::class)->process());
        $this->assertSame('support_required', $travel->fresh()->status);
        $this->assertSame('support_required', $confirmed->fresh()->status);
        $this->assertSame('revoked', $voucher->fresh()->status);
        $this->assertDatabaseHas('travel_financial_events', [
            'travel_fulfillment_id' => $confirmed->id,
            'type' => 'refund_review_required',
        ]);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_unavailable_adapter_never_fabricates_confirmation(): void
    {
        [$staff, $guest, $supplier, $travel] = $this->fixture('transfer');
        $fulfillment = app(TravelFulfillmentService::class)->recordVerifiedCapture($travel, 'sandbox-paid-0003');
        config()->set('travel.supplier_adapters', []);
        $this->expectException(ValidationException::class);
        app(TravelFulfillmentService::class)->requestSupplierReservation($fulfillment);
    }

    public function test_disabled_fulfillment_rejects_even_sandbox_payment(): void
    {
        [$staff, $guest, $supplier, $travel] = $this->fixture('transfer');
        config()->set('travel.fulfillment_enabled', false);
        $this->expectException(ValidationException::class);
        app(TravelFulfillmentService::class)->recordVerifiedCapture($travel, 'sandbox-paid-0004');
    }
}
