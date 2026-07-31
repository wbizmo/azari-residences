<?php

namespace Tests\Feature;

use App\Services\Payments\FlutterwaveService;
use App\Services\Payments\InTouchService;
use App\Services\Payments\PesapalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SprintEightProviderContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_flutterwave_v4_oauth_initialisation_verification_and_signature(): void
    {
        Cache::clear();
        $this->configureFlutterwaveV4();

        Http::fake([
            'https://idp.flutterwave.test/token' => Http::response([
                'access_token' => 'oauth-token',
                'expires_in' => 600,
                'token_type' => 'Bearer',
            ], 200),
            'https://api.flutterwave.test/orchestration/direct-charges' => Http::response([
                'status' => 'success',
                'message' => 'Charge created',
                'data' => [
                    'id' => 'chg_44',
                    'reference' => 'PAY-44',
                    'status' => 'pending',
                    'next_action' => [
                        'type' => 'redirect_url',
                        'redirect_url' => ['url' => 'https://checkout.test/opay'],
                    ],
                ],
            ], 201),
            'https://api.flutterwave.test/charges/chg_44' => Http::response([
                'status' => 'success',
                'message' => 'Charge fetched',
                'data' => [
                    'id' => 'chg_44',
                    'reference' => 'PAY-44',
                    'status' => 'succeeded',
                    'amount' => 500,
                    'currency' => 'NGN',
                    'payment_method_details' => ['type' => 'opay'],
                    'created_datetime' => '2026-07-31T06:00:00Z',
                ],
            ], 200),
        ]);

        $driver = app(FlutterwaveService::class);
        $initial = $driver->initialise([
            ...$this->payload('PAY-44'),
            'provider_options' => ['flutterwave_payment_method' => 'opay'],
            'instructions_url' => 'https://azari.test/instructions',
        ]);
        $verified = $driver->verify('chg_44');
        $raw = '{"webhook_id":"wbk_1","type":"charge.completed"}';
        $signature = base64_encode(hash_hmac('sha256', $raw, 'hook-secret', true));

        $this->assertSame('https://checkout.test/opay', $initial['checkout_url']);
        $this->assertSame('chg_44', $initial['provider_reference']);
        $this->assertSame('successful', $verified['status']);
        $this->assertSame('PAY-44', $verified['merchant_reference']);
        $this->assertTrue($driver->webhookSignatureIsValid(
            $raw,
            ['flutterwave-signature' => [$signature]]
        ));
        Http::assertSentCount(3);
    }

    public function test_flutterwave_v4_ussd_returns_local_instruction_url(): void
    {
        Cache::clear();
        $this->configureFlutterwaveV4();

        Http::fake([
            'https://idp.flutterwave.test/token' => Http::response([
                'access_token' => 'oauth-token',
                'expires_in' => 600,
            ], 200),
            'https://api.flutterwave.test/orchestration/direct-charges' => Http::response([
                'status' => 'success',
                'message' => 'Charge created',
                'data' => [
                    'id' => 'chg_ussd',
                    'reference' => 'PAY-USSD',
                    'status' => 'pending',
                    'next_action' => [
                        'type' => 'payment_instruction',
                        'payment_instruction' => [
                            'note' => 'Please dial *1414# to complete this transaction',
                        ],
                    ],
                ],
            ], 201),
        ]);

        $result = app(FlutterwaveService::class)->initialise([
            ...$this->payload('PAY-USSD'),
            'provider_options' => [
                'flutterwave_payment_method' => 'ussd',
                'flutterwave_ussd_bank' => '044',
            ],
            'instructions_url' => 'https://azari.test/instructions/PAY-USSD',
        ]);

        $this->assertSame('https://azari.test/instructions/PAY-USSD', $result['checkout_url']);
        $this->assertSame('chg_ussd', $result['provider_reference']);
        $this->assertSame(
            'Please dial *1414# to complete this transaction',
            $result['safe_response']['payment_instruction']
        );
        Http::assertSentCount(2);
    }

    public function test_pesapal_uses_token_submit_and_server_status_verification(): void
    {
        Cache::clear();
        config()->set('azari.payments.pesapal', [
            'enabled' => true,
            'mode' => 'sandbox',
            'base_url' => 'https://pesapal.test',
            'consumer_key' => 'key',
            'consumer_secret' => 'secret',
            'notification_id' => 'notification',
            'callback_url' => 'https://azari.test/payments/pesapal/callback',
            'auth_path' => '/api/Auth/RequestToken',
            'submit_order_path' => '/api/Transactions/SubmitOrderRequest',
            'status_path' => '/api/Transactions/GetTransactionStatus',
        ]);
        Http::fake([
            'https://pesapal.test/api/Auth/RequestToken' => Http::response(['token' => 'token'], 200),
            'https://pesapal.test/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'TRACK',
                'merchant_reference' => 'PAY-55',
                'redirect_url' => 'https://pesapal-checkout.test',
            ], 200),
            'https://pesapal.test/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_status_description' => 'COMPLETED',
                'merchant_reference' => 'PAY-55',
                'amount' => 500,
                'currency' => 'USD',
                'payment_method' => 'Mobile Money',
            ], 200),
        ]);
        $driver = app(PesapalService::class);
        $this->assertSame(
            'https://pesapal-checkout.test',
            $driver->initialise($this->payload('PAY-55'))['checkout_url']
        );
        $this->assertSame('successful', $driver->verify('TRACK')['status']);
    }

    public function test_intouch_adapter_is_environment_configurable_and_verifies_hmac(): void
    {
        config()->set('azari.payments.intouch', [
            'enabled' => true,
            'mode' => 'test',
            'profile' => 'custom_v1',
            'base_url' => 'https://intouch.test',
            'merchant_id' => 'merchant',
            'username' => 'user',
            'password' => 'pass',
            'secret' => 'api-secret',
            'webhook_secret' => 'webhook-secret',
            'callback_url' => 'https://azari.test/payments/intouch/callback',
            'auth_mode' => 'basic_and_headers',
            'merchant_header' => 'X-Merchant-ID',
            'secret_header' => 'X-API-Secret',
            'webhook_signature_header' => 'x-intouch-signature',
            'initialise_path' => '/start',
            'verify_path' => '/status/{reference}',
            'health_path' => '',
            'checkout_url_field' => 'checkout_url',
            'provider_reference_field' => 'transaction_id',
            'merchant_reference_field' => 'merchant_reference',
            'status_field' => 'status',
            'amount_field' => 'amount',
            'currency_field' => 'currency',
            'successful_statuses' => ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'],
            'failed_statuses' => ['FAILED', 'DECLINED', 'CANCELLED', 'INVALID'],
        ]);
        Http::fake([
            'https://intouch.test/start' => Http::response([
                'data' => [
                    'transaction_id' => 'I-1',
                    'checkout_url' => 'https://intouch-checkout.test',
                ],
            ], 200),
            'https://intouch.test/status/I-1*' => Http::response([
                'data' => [
                    'transaction_id' => 'I-1',
                    'merchant_reference' => 'PAY-66',
                    'status' => 'SUCCESSFUL',
                    'amount' => 500,
                    'currency' => 'USD',
                ],
            ], 200),
        ]);
        $driver = app(InTouchService::class);
        $this->assertSame(
            'https://intouch-checkout.test',
            $driver->initialise($this->payload('PAY-66'))['checkout_url']
        );
        $this->assertSame('successful', $driver->verify('I-1')['status']);
        $raw = '{"transaction_id":"I-1"}';
        $signature = hash_hmac('sha256', $raw, 'webhook-secret');
        $this->assertTrue($driver->webhookSignatureIsValid(
            $raw,
            ['x-intouch-signature' => [$signature]]
        ));
    }

    private function configureFlutterwaveV4(): void
    {
        config()->set('azari.payments.flutterwave', [
            'enabled' => true,
            'mode' => 'test',
            'api_version' => 'v4',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'webhook_secret' => 'hook-secret',
            'token_url' => 'https://idp.flutterwave.test/token',
            'sandbox_base_url' => 'https://api.flutterwave.test',
            'live_base_url' => 'https://live.flutterwave.test',
            'orchestrator_path' => '/orchestration/direct-charges',
            'charge_path' => '/charges/{id}',
            'banks_path' => '/banks',
            'allowed_payment_methods' => ['opay', 'ussd'],
            'default_payment_method' => 'opay',
            'token_cache_seconds' => 540,
        ]);
    }

    private function payload(string $reference): array
    {
        return [
            'reference' => $reference,
            'booking_reference' => 'AZR-001',
            'amount' => 500,
            'currency' => 'NGN',
            'name' => 'Guest User',
            'first_name' => 'Guest',
            'last_name' => 'User',
            'email' => 'guest@example.com',
            'phone' => '+2348000000000',
            'callback_url' => 'https://azari.test/callback',
            'webhook_url' => 'https://azari.test/webhook',
        ];
    }
}
