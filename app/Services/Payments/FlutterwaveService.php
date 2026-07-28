<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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

        $request = [
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
        ];

        if (config('azari.payments.flutterwave.payload_hash_enabled')) {
            $request['payload_hash'] = $this->payloadHash(
                (string) $request['amount'],
                (string) $request['currency'],
                (string) $request['customer']['email'],
                (string) $request['tx_ref'],
            );
        }

        // A checkout creation POST is deliberately not automatically retried:
        // a lost response does not prove that Flutterwave did not create it.
        $response = $this->request()->post(
            (string) config('azari.payments.flutterwave.initialise_path', '/v3/payments'),
            $request,
        );

        $this->ensureSuccessful($response, 'Unable to initialise Flutterwave checkout.');
        $data = (array) $response->json('data', []);
        $url = (string) ($data['link'] ?? '');

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new PaymentProviderException('Flutterwave did not return a valid checkout URL.', $this->name(), $response->status());
        }

        return [
            'checkout_url' => $url,
            'provider_reference' => null,
            'safe_response' => [
                'status' => $response->json('status'),
                'message' => $response->json('message'),
            ],
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();

        if ($providerReference === '') {
            throw new PaymentProviderException('Flutterwave transaction ID is missing.', $this->name());
        }

        $path = str_replace(
            '{id}',
            rawurlencode($providerReference),
            (string) config('azari.payments.flutterwave.verify_path', '/v3/transactions/{id}/verify'),
        );

        $response = $this->safeGet($path);
        $this->ensureSuccessful($response, 'Unable to verify Flutterwave payment.');

        $data = (array) $response->json('data', []);
        $status = strtolower((string) ($data['status'] ?? 'pending'));

        return [
            'status' => in_array($status, ['successful', 'success', 'succeeded'], true)
                ? 'successful'
                : (in_array($status, ['failed', 'cancelled', 'canceled'], true) ? 'failed' : 'pending'),
            'provider_status' => $status,
            'provider_reference' => (string) ($data['id'] ?? $providerReference),
            'merchant_reference' => (string) ($data['tx_ref'] ?? $data['reference'] ?? ''),
            'amount' => (float) ($data['amount'] ?? $data['charged_amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'payment_method' => (string) ($data['payment_type'] ?? data_get($data, 'payment_method.type', '')),
            'paid_at' => $data['created_at'] ?? $data['created_datetime'] ?? null,
            'safe_response' => Arr::only($data, [
                'id', 'tx_ref', 'reference', 'status', 'amount', 'charged_amount',
                'currency', 'payment_type', 'created_at', 'created_datetime',
            ]),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        $secret = (string) config('azari.payments.flutterwave.webhook_secret');
        if ($secret === '') return false;

        $signature = $this->header($headers, 'flutterwave-signature');
        if ($signature !== '') {
            $expected = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));
            return hash_equals($expected, trim($signature));
        }

        // Compatibility for accounts still delivering the API-v3 secret hash.
        $legacy = $this->header($headers, 'verif-hash');
        return $legacy !== '' && hash_equals($secret, trim($legacy));
    }

    public function webhookReferences(array $payload): array
    {
        $data = (array) ($payload['data'] ?? []);

        return [
            'event_id' => (string) (
                $payload['id']
                ?? $payload['webhook_id']
                ?? hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES))
            ),
            'event_type' => (string) ($payload['type'] ?? $payload['event'] ?? 'payment.webhook'),
            'merchant_reference' => (string) (
                $data['tx_ref']
                ?? $data['reference']
                ?? $payload['tx_ref']
                ?? $payload['reference']
                ?? ''
            ),
            'provider_reference' => (string) (
                $data['id']
                ?? $payload['transaction_id']
                ?? ''
            ),
        ];
    }

    public function healthCheck(): array
    {
        if (! $this->enabled()) return ['successful' => false, 'message' => 'Provider is disabled.'];

        try {
            $this->assertConfigured();
            // Verification is a safer capability test than creating a charge.
            return ['successful' => true, 'message' => 'Configuration is complete.'];
        } catch (\Throwable) {
            return ['successful' => false, 'message' => 'Configuration is incomplete.'];
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('azari.payments.flutterwave.base_url'), '/'))
            ->withToken((string) config('azari.payments.flutterwave.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('azari.integrations.http_timeout', 20))
            ->connectTimeout((int) config('azari.integrations.connect_timeout', 8));
    }

    private function safeGet(string $path): Response
    {
        return $this->request()->retry(
            2,
            300,
            fn (\Throwable $exception) => $exception instanceof ConnectionException,
            throw: false,
        )->get($path);
    }

    private function assertConfigured(): void
    {
        $base = (string) config('azari.payments.flutterwave.base_url');

        if (! $this->enabled()
            || blank(config('azari.payments.flutterwave.secret_key'))
            || blank(config('azari.payments.flutterwave.webhook_secret'))
        ) {
            throw new PaymentProviderException('Flutterwave is not completely configured.', $this->name());
        }

        $this->assertSecureBaseUrl($base);
    }

    private function assertSecureBaseUrl(string $url): void
    {
        if (app()->environment('production')
            && config('azari.integrations.require_https_in_production')
            && ! str_starts_with(strtolower($url), 'https://')
        ) {
            throw new PaymentProviderException('Flutterwave must use HTTPS in production.', $this->name());
        }
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful() || strtolower((string) $response->json('status')) === 'error') {
            throw new PaymentProviderException(
                $message,
                $this->name(),
                $response->status(),
                ['message' => $response->json('message')]
            );
        }
    }

    private function payloadHash(string $amount, string $currency, string $email, string $reference): string
    {
        $secretHash = hash('sha256', (string) config('azari.payments.flutterwave.secret_key'));

        return hash('sha256', $amount.$currency.$email.$reference.$secretHash);
    }

    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return (string) (is_array($value) ? ($value[0] ?? '') : $value);
            }
        }

        return '';
    }
}
