<?php

namespace Tests\Feature\Integrations;

use App\Models\CommunicationLog;
use App\Services\Communication\TwilioSmsService;
use App\Services\Payments\FlutterwaveService;
use App\Services\Payments\InTouchService;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\PesapalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalIntegrationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_flutterwave_current_hmac_signature_is_verified(): void
    {
        config()->set('azari.payments.flutterwave.webhook_secret', 'secret');
        $raw = '{"id":"wbk_1","type":"charge.completed","data":{"id":"chg_1"}}';
        $signature = base64_encode(hash_hmac('sha256', $raw, 'secret', true));

        $this->assertTrue(app(FlutterwaveService::class)->webhookSignatureIsValid(
            $raw,
            ['flutterwave-signature' => [$signature]],
        ));
    }

    public function test_flutterwave_initialisation_is_not_retried_automatically(): void
    {
        config()->set('azari.payments.flutterwave.enabled', true);
        config()->set('azari.payments.flutterwave.secret_key', 'secret');
        config()->set('azari.payments.flutterwave.webhook_secret', 'webhook');
        config()->set('azari.payments.flutterwave.base_url', 'https://api.flutterwave.test');

        Http::fake([
            'api.flutterwave.test/*' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.test/pay'],
            ]),
        ]);

        app(FlutterwaveService::class)->initialise([
            'reference' => 'PAY-1',
            'booking_reference' => 'AZR-1',
            'amount' => 100,
            'currency' => 'USD',
            'callback_url' => 'https://azari.test/callback',
            'email' => 'guest@example.com',
            'name' => 'Guest',
            'phone' => '+2348000000000',
        ]);

        Http::assertSentCount(1);
    }

    public function test_pesapal_ipn_endpoint_returns_api_three_acknowledgement_shape(): void
    {
        config()->set('azari.payments.pesapal.enabled', true);
        config()->set('azari.payments.pesapal.consumer_key', 'key');
        config()->set('azari.payments.pesapal.consumer_secret', 'secret');
        config()->set('azari.payments.pesapal.notification_id', 'notification');

        $response = $this->postJson('/payments/pesapal/webhook', [
            'OrderTrackingId' => 'tracking-id',
            'OrderMerchantReference' => 'missing-payment',
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $response->assertOk()->assertJsonStructure([
            'orderNotificationType',
            'orderTrackingId',
            'orderMerchantReference',
            'status',
        ]);
    }

    public function test_intouch_fails_closed_without_confirmed_api_profile(): void
    {
        config()->set('azari.payments.intouch.enabled', true);
        config()->set('azari.payments.intouch.profile', '');
        config()->set('azari.payments.intouch.base_url', 'https://intouch.test');
        config()->set('azari.payments.intouch.merchant_id', 'merchant');
        config()->set('azari.payments.intouch.webhook_secret', 'secret');
        config()->set('azari.payments.intouch.callback_url', 'https://azari.test/callback');

        $health = app(InTouchService::class)->healthCheck();

        $this->assertFalse($health['successful']);
        $this->assertSame('Configuration is incomplete.', $health['message']);

        $this->expectException(PaymentProviderException::class);

        app(InTouchService::class)->initialise([
            'reference' => 'PAY-INT-1',
            'booking_reference' => 'AZR-INT-1',
            'amount' => 100,
            'currency' => 'USD',
            'callback_url' => 'https://azari.test/callback',
            'webhook_url' => 'https://azari.test/payments/intouch/webhook',
            'email' => 'guest@example.com',
            'name' => 'Guest',
            'phone' => '+2348000000000',
        ]);
    }

    public function test_twilio_submission_uses_one_api_request_and_records_sid(): void
    {
        config()->set('services.twilio.enabled', true);
        config()->set('services.twilio.sid', 'AC'.str_repeat('1', 32));
        config()->set('services.twilio.token', 'token');
        config()->set('services.twilio.messaging_service_sid', 'MG'.str_repeat('2', 32));
        config()->set('services.twilio.api_base_url', 'https://api.twilio.test');
        config()->set('services.twilio.status_callback', 'https://azari.test/webhooks/twilio/message-status');

        Http::fake([
            'api.twilio.test/*' => Http::response([
                'sid' => 'SM'.str_repeat('3', 32),
                'status' => 'queued',
            ], 201),
        ]);

        $result = app(TwilioSmsService::class)->send('+2348000000000', 'Hello');

        $this->assertSame('SM'.str_repeat('3', 32), $result['sid']);
        Http::assertSentCount(1);
    }

    public function test_twilio_status_callback_rejects_unsigned_requests(): void
    {
        config()->set('services.twilio.validate_webhooks', true);
        config()->set('services.twilio.token', 'token');
        config()->set('services.twilio.status_callback', 'https://azari.test/webhooks/twilio/message-status');

        $this->post('/webhooks/twilio/message-status', [
            'MessageSid' => 'SM'.str_repeat('3', 32),
            'MessageStatus' => 'delivered',
        ])->assertUnauthorized();
    }
}
