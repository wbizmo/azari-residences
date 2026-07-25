<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final class InTouchService implements PaymentProvider
{
    public function name(): string { return 'intouch'; }
    public function enabled(): bool { return (bool) config('azari.payments.intouch.enabled'); }
    public function mode(): string { return (string) config('azari.payments.intouch.mode', 'test'); }

    public function initialise(array $payload): array
    {
        $this->assertConfigured();
        $response = $this->request()->post((string) config('azari.payments.intouch.initialise_path'), [
            'merchant_id' => config('azari.payments.intouch.merchant_id'),
            'merchant_reference' => $payload['reference'],
            'booking_reference' => $payload['booking_reference'],
            'amount' => round((float) $payload['amount'], 2),
            'currency' => strtoupper($payload['currency']),
            'customer_name' => $payload['name'],
            'customer_email' => $payload['email'],
            'customer_phone' => $payload['phone'] ?? '',
            'callback_url' => config('azari.payments.intouch.callback_url') ?: $payload['callback_url'],
            'webhook_url' => $payload['webhook_url'] ?? null,
        ]);
        $this->ensureSuccessful($response, 'Unable to initialise InTouch checkout.');
        $data = (array) ($response->json('data') ?? $response->json());
        $url = (string) ($data['checkout_url'] ?? $data['redirect_url'] ?? $data['payment_url'] ?? '');
        if ($url === '') throw new PaymentProviderException('InTouch did not return a checkout URL.', $this->name(), $response->status());

        return [
            'checkout_url' => $url,
            'provider_reference' => (string) ($data['transaction_id'] ?? $data['reference'] ?? ''),
            'safe_response' => Arr::only($data, ['transaction_id', 'reference', 'status', 'message']),
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();
        $path = str_replace('{reference}', rawurlencode($providerReference), (string) config('azari.payments.intouch.verify_path'));
        $response = $this->request()->get($path, ['reference' => $providerReference, 'merchant_id' => config('azari.payments.intouch.merchant_id')]);
        $this->ensureSuccessful($response, 'Unable to verify InTouch payment.');
        $data = (array) ($response->json('data') ?? $response->json());
        $providerStatus = strtoupper((string) ($data['status'] ?? $data['transaction_status'] ?? 'PENDING'));

        return [
            'status' => in_array($providerStatus, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'], true) ? 'successful' : (in_array($providerStatus, ['FAILED', 'DECLINED', 'CANCELLED', 'INVALID'], true) ? 'failed' : 'pending'),
            'provider_status' => $providerStatus,
            'provider_reference' => (string) ($data['transaction_id'] ?? $data['reference'] ?? $providerReference),
            'merchant_reference' => (string) ($data['merchant_reference'] ?? $data['request_reference'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'payment_method' => (string) ($data['payment_method'] ?? $data['channel'] ?? ''),
            'safe_response' => Arr::only($data, ['transaction_id', 'reference', 'merchant_reference', 'status', 'transaction_status', 'amount', 'currency', 'payment_method', 'channel']),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        $secret = (string) config('azari.payments.intouch.webhook_secret');
        if ($secret === '') return false;
        $signature = $this->header($headers, 'x-intouch-signature') ?: $this->header($headers, 'x-signature');
        if ($signature === '') return false;
        $hex = hash_hmac('sha256', $rawPayload, $secret);
        $base64 = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));
        return hash_equals($hex, $signature) || hash_equals($base64, $signature);
    }

    public function webhookReferences(array $payload): array
    {
        $data = (array) ($payload['data'] ?? $payload);
        $provider = (string) ($data['transaction_id'] ?? $data['reference'] ?? '');
        $merchant = (string) ($data['merchant_reference'] ?? $data['request_reference'] ?? '');
        return [
            'event_id' => (string) ($payload['event_id'] ?? $payload['id'] ?? hash('sha256', json_encode($payload))),
            'event_type' => (string) ($payload['event'] ?? $payload['type'] ?? 'payment.webhook'),
            'merchant_reference' => $merchant,
            'provider_reference' => $provider,
        ];
    }

    public function healthCheck(): array
    {
        if (! $this->enabled()) return ['successful' => false, 'message' => 'Provider is disabled.'];
        try {
            $this->assertConfigured();
            $path = (string) config('azari.payments.intouch.health_path');
            if ($path === '') return ['successful' => true, 'message' => 'Configuration is complete.'];
            $response = $this->request()->get($path);
            return ['successful' => $response->successful(), 'message' => $response->successful() ? 'Connection successful.' : 'Connection failed.'];
        } catch (\Throwable $e) {
            return ['successful' => false, 'message' => 'Connection failed.'];
        }
    }

    private function request()
    {
        return Http::baseUrl((string) config('azari.payments.intouch.base_url'))
            ->withBasicAuth((string) config('azari.payments.intouch.username'), (string) config('azari.payments.intouch.password'))
            ->withHeaders([
                'X-Merchant-ID' => (string) config('azari.payments.intouch.merchant_id'),
                'X-API-Secret' => (string) config('azari.payments.intouch.secret'),
            ])->acceptJson()->asJson()->timeout(20)->connectTimeout(8)->retry(2, 300, throw: false);
    }

    private function assertConfigured(): void
    {
        if (! $this->enabled() || blank(config('azari.payments.intouch.base_url')) || blank(config('azari.payments.intouch.merchant_id')) || blank(config('azari.payments.intouch.secret'))) {
            throw new PaymentProviderException('InTouch is not configured.', $this->name());
        }
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new PaymentProviderException($message, $this->name(), $response->status(), ['message' => $response->json('message')]);
        }
    }

    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) return (string) (is_array($value) ? ($value[0] ?? '') : $value);
        }
        return '';
    }
}
