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

    public function test_flutterwave_initialisation_verification_and_signature(): void
    {
        config()->set('azari.payments.flutterwave', [
            'enabled' => true, 'mode' => 'test', 'base_url' => 'https://api.flutterwave.test',
            'secret_key' => 'secret', 'webhook_secret' => 'hook-secret',
            'initialise_path' => '/v3/payments', 'verify_path' => '/v3/transactions/{id}/verify',
        ]);
        Http::fake([
            'https://api.flutterwave.test/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test']], 200),
            'https://api.flutterwave.test/v3/transactions/44/verify' => Http::response(['status' => 'success', 'data' => ['id' => 44, 'tx_ref' => 'PAY-44', 'status' => 'successful', 'amount' => 500, 'currency' => 'USD', 'payment_type' => 'card']], 200),
        ]);
        $driver = app(FlutterwaveService::class);
        $initial = $driver->initialise($this->payload('PAY-44'));
        $verified = $driver->verify('44');
        $raw = '{"event":"charge.completed"}';
        $signature = base64_encode(hash_hmac('sha256', $raw, 'hook-secret', true));

        $this->assertSame('https://checkout.test', $initial['checkout_url']);
        $this->assertSame('successful', $verified['status']);
        $this->assertTrue($driver->webhookSignatureIsValid($raw, ['flutterwave-signature' => [$signature]]));
    }

    public function test_pesapal_uses_token_submit_and_server_status_verification(): void
    {
        Cache::clear();
        config()->set('azari.payments.pesapal', [
            'enabled' => true, 'mode' => 'sandbox', 'base_url' => 'https://pesapal.test',
            'consumer_key' => 'key', 'consumer_secret' => 'secret', 'notification_id' => 'notification',
            'callback_url' => 'https://azari.test/payments/pesapal/callback',
            'auth_path' => '/api/Auth/RequestToken',
            'submit_order_path' => '/api/Transactions/SubmitOrderRequest',
            'status_path' => '/api/Transactions/GetTransactionStatus',
        ]);
        Http::fake([
            'https://pesapal.test/api/Auth/RequestToken' => Http::response(['token' => 'token'], 200),
            'https://pesapal.test/api/Transactions/SubmitOrderRequest' => Http::response(['order_tracking_id' => 'TRACK', 'merchant_reference' => 'PAY-55', 'redirect_url' => 'https://pesapal-checkout.test'], 200),
            'https://pesapal.test/api/Transactions/GetTransactionStatus*' => Http::response(['payment_status_description' => 'COMPLETED', 'merchant_reference' => 'PAY-55', 'amount' => 500, 'currency' => 'USD', 'payment_method' => 'Mobile Money'], 200),
        ]);
        $driver = app(PesapalService::class);
        $this->assertSame('https://pesapal-checkout.test', $driver->initialise($this->payload('PAY-55'))['checkout_url']);
        $this->assertSame('successful', $driver->verify('TRACK')['status']);
    }

    public function test_intouch_adapter_is_environment_configurable_and_verifies_hmac(): void
    {
        config()->set('azari.payments.intouch', [
            'enabled' => true, 'mode' => 'test', 'profile' => 'custom_v1', 'base_url' => 'https://intouch.test',
            'merchant_id' => 'merchant', 'username' => 'user', 'password' => 'pass', 'secret' => 'api-secret',
            'webhook_secret' => 'webhook-secret', 'callback_url' => 'https://azari.test/payments/intouch/callback',
            'auth_mode' => 'basic_and_headers', 'merchant_header' => 'X-Merchant-ID', 'secret_header' => 'X-API-Secret',
            'webhook_signature_header' => 'x-intouch-signature',
            'initialise_path' => '/start', 'verify_path' => '/status/{reference}', 'health_path' => '',
            'checkout_url_field' => 'checkout_url', 'provider_reference_field' => 'transaction_id',
            'merchant_reference_field' => 'merchant_reference', 'status_field' => 'status',
            'amount_field' => 'amount', 'currency_field' => 'currency',
            'successful_statuses' => ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'],
            'failed_statuses' => ['FAILED', 'DECLINED', 'CANCELLED', 'INVALID'],
        ]);
        Http::fake([
            'https://intouch.test/start' => Http::response(['data' => ['transaction_id' => 'I-1', 'checkout_url' => 'https://intouch-checkout.test']], 200),
            'https://intouch.test/status/I-1*' => Http::response(['data' => ['transaction_id' => 'I-1', 'merchant_reference' => 'PAY-66', 'status' => 'SUCCESSFUL', 'amount' => 500, 'currency' => 'USD']], 200),
        ]);
        $driver = app(InTouchService::class);
        $this->assertSame('https://intouch-checkout.test', $driver->initialise($this->payload('PAY-66'))['checkout_url']);
        $this->assertSame('successful', $driver->verify('I-1')['status']);
        $raw = '{"transaction_id":"I-1"}';
        $signature = hash_hmac('sha256', $raw, 'webhook-secret');
        $this->assertTrue($driver->webhookSignatureIsValid($raw, ['x-intouch-signature' => [$signature]]));
    }

    private function payload(string $reference): array
    {
        return [
            'reference' => $reference, 'booking_reference' => 'AZR-001', 'amount' => 500,
            'currency' => 'USD', 'name' => 'Guest User', 'first_name' => 'Guest', 'last_name' => 'User',
            'email' => 'guest@example.com', 'phone' => '+2348000000000',
            'callback_url' => 'https://azari.test/callback', 'webhook_url' => 'https://azari.test/webhook',
        ];
    }
}
