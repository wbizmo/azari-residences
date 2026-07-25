<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final class FlutterwaveService implements PaymentProvider
{
    public function name(): string { return 'flutterwave'; }
    public function enabled(): bool { return (bool) config('azari.payments.flutterwave.enabled'); }
    public function mode(): string { return (string) config('azari.payments.flutterwave.mode', 'test'); }

    public function initialise(array $payload): array
    {
        $this->assertConfigured();

        $response = $this->request()->post('/v3/payments', [
            'tx_ref' => $payload['reference'],
            'amount' => number_format((float) $payload['amount'], 2, '.', ''),
            'currency' => strtoupper($payload['currency']),
            'redirect_url' => $payload['callback_url'],
            'customer' => [
                'email' => $payload['email'],
                'name' => $payload['name'],
                'phonenumber' => $payload['phone'] ?? null,
            ],
            'customizations' => [
                'title' => config('app.name', 'Azari Residences'),
                'description' => 'Payment for booking '.$payload['booking_reference'],
            ],
            'meta' => [
                'booking_reference' => $payload['booking_reference'],
                'payment_reference' => $payload['reference'],
            ],
        ]);

        $this->ensureSuccessful($response, 'Unable to initialise Flutterwave checkout.');
        $data = (array) $response->json('data', []);
        $url = (string) ($data['link'] ?? '');
        if ($url === '') {
            throw new PaymentProviderException('Flutterwave did not return a checkout URL.', $this->name(), $response->status());
        }

        return [
            'checkout_url' => $url,
            'provider_reference' => null,
            'safe_response' => ['status' => $response->json('status'), 'message' => $response->json('message')],
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();
        if ($providerReference === '') {
            throw new PaymentProviderException('Flutterwave transaction ID is missing.', $this->name());
        }

        $response = $this->request()->get('/v3/transactions/'.rawurlencode($providerReference).'/verify');
        $this->ensureSuccessful($response, 'Unable to verify Flutterwave payment.');
        $data = (array) $response->json('data', []);
        $status = strtolower((string) ($data['status'] ?? 'pending'));

        return [
            'status' => in_array($status, ['successful', 'success'], true) ? 'successful' : (in_array($status, ['failed', 'cancelled'], true) ? 'failed' : 'pending'),
            'provider_status' => $status,
            'provider_reference' => (string) ($data['id'] ?? $providerReference),
            'merchant_reference' => (string) ($data['tx_ref'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'payment_method' => (string) ($data['payment_type'] ?? ''),
            'safe_response' => Arr::only($data, ['id', 'tx_ref', 'status', 'amount', 'currency', 'payment_type', 'created_at']),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        $secret = (string) config('azari.payments.flutterwave.webhook_secret');
        if ($secret === '') return false;

        $signature = $this->header($headers, 'flutterwave-signature');
        if ($signature !== '') {
            $expected = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));
            return hash_equals($expected, $signature);
        }

        $legacy = $this->header($headers, 'verif-hash');
        return $legacy !== '' && hash_equals($secret, $legacy);
    }

    public function webhookReferences(array $payload): array
    {
        $data = (array) ($payload['data'] ?? []);
        return [
            'event_id' => (string) ($payload['webhook_id'] ?? hash('sha256', json_encode($payload))),
            'event_type' => (string) ($payload['event'] ?? $payload['type'] ?? 'payment.webhook'),
            'merchant_reference' => (string) ($data['tx_ref'] ?? $payload['tx_ref'] ?? ''),
            'provider_reference' => (string) ($data['id'] ?? $payload['transaction_id'] ?? ''),
        ];
    }

    public function healthCheck(): array
    {
        if (! $this->enabled()) return ['successful' => false, 'message' => 'Provider is disabled.'];
        try {
            $this->assertConfigured();
            $response = $this->request()->get('/v3/balances/NGN');
            return ['successful' => $response->successful(), 'message' => $response->successful() ? 'Connection successful.' : 'Connection failed.'];
        } catch (\Throwable $e) {
            return ['successful' => false, 'message' => 'Connection failed.'];
        }
    }

    private function request()
    {
        return Http::baseUrl((string) config('azari.payments.flutterwave.base_url'))
            ->withToken((string) config('azari.payments.flutterwave.secret_key'))
            ->acceptJson()->asJson()->timeout(20)->connectTimeout(8)->retry(2, 300, throw: false);
    }

    private function assertConfigured(): void
    {
        if (! $this->enabled() || blank(config('azari.payments.flutterwave.secret_key'))) {
            throw new PaymentProviderException('Flutterwave is not configured.', $this->name());
        }
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful() || strtolower((string) $response->json('status')) === 'error') {
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
