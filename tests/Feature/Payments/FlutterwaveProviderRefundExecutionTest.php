<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\ProviderRefundExecutionService;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FlutterwaveProviderRefundExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        foreach ([
            'enabled' => true,
            'mode' => 'test',
            'api_version' => 'v4',
            'client_id' => 'refund-test-client',
            'client_secret' => 'refund-test-secret',
            'webhook_secret' => 'refund-test-webhook-secret',
            'token_url' => 'https://idp.flutterwave.test/token',
            'sandbox_base_url' => 'https://api.flutterwave.test',
        ] as $key => $value) {
            config()->set('azari.payments.flutterwave.'.$key, $value);
        }
    }

    private function refund(): Refund
    {
        $booking = Booking::factory()->create(['total' => 100]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->getKey(), 'provider' => 'flutterwave',
            'provider_reference' => 'chg-test-001', 'reference' => 'PAY-FLW-RFD-001',
            'amount' => 100, 'currency' => 'USD', 'status' => Payment::SUCCESSFUL,
        ]);
        return app(RefundService::class)->request($payment, 40, null, 'Guest request', 'flw-refund-key');
    }

    private function fakeGateway(string $chargeId = 'chg-test-001', string $status = 'succeeded'): void
    {
        Http::fake([
            'https://idp.flutterwave.test/token' => Http::response(['access_token' => 'test-access-token'], 200),
            'https://api.flutterwave.test/refunds' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'rfd-test-001', 'charge_id' => 'chg-test-001',
                    'amount_refunded' => 40, 'status' => 'pending',
                ],
            ], 201),
            'https://api.flutterwave.test/refunds/rfd-test-001' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'rfd-test-001', 'charge_id' => $chargeId,
                    'amount_refunded' => 40, 'status' => $status,
                ],
            ], 200),
        ]);
    }

    public function test_provider_acceptance_is_not_settlement_and_get_verification_finishes_refund(): void
    {
        $this->fakeGateway();
        $refund = $this->refund();
        $service = app(ProviderRefundExecutionService::class);

        $submitted = $service->dispatch($refund);
        $this->assertSame('processing', $submitted->status);
        $this->assertSame('rfd-test-001', $submitted->provider_reference);
        $this->assertSame(0, (int) $submitted->payment->refunded_amount);

        $verified = $service->reconcile($submitted);
        $this->assertSame('successful', $verified->status);
        $this->assertSame('rfd-test-001', $verified->provider_reference);
        $this->assertSame(40.0, (float) $verified->payment->fresh()->refunded_amount);
        $this->assertSame('successful', $service->reconcile($verified)->status);
        Http::assertSentCount(3);
    }

    public function test_duplicate_submission_is_rejected_before_second_remote_call(): void
    {
        $this->fakeGateway();
        $service = app(ProviderRefundExecutionService::class);
        $refund = $service->dispatch($this->refund());
        try {
            $service->dispatch($refund->fresh());
            $this->fail('Repeated payout commands must not send another refund.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('refund', $e->errors());
        }
        Http::assertSentCount(2);
    }

    public function test_mismatched_charge_reference_never_settles_refund(): void
    {
        $this->fakeGateway(chargeId: 'chg-other-account');
        $service = app(ProviderRefundExecutionService::class);
        $refund = $service->dispatch($this->refund());
        try {
            $service->reconcile($refund);
            $this->fail('Unmatched provider confirmation must not settle local balance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('refund', $e->errors());
        }
        $this->assertSame('processing', $refund->fresh()->status);
    }

    public function test_pending_provider_status_keeps_money_unsettled(): void
    {
        $this->fakeGateway(status: 'pending');
        $service = app(ProviderRefundExecutionService::class);
        $refund = $service->dispatch($this->refund());
        $this->assertSame('processing', $service->reconcile($refund)->status);
        $this->assertDatabaseMissing('refunds', ['id' => $refund->id, 'status' => 'successful']);
    }
}
