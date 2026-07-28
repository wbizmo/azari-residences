<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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

        $response = $this->request()->post(
            (string) config('azari.payments.intouch.initialise_path'),
            [
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
            ],
        );

        $this->ensureSuccessful($response, 'Unable to initialise InTouch checkout.');
        $data = (array) ($response->json('data') ?? $response->json());

        $url = (string) data_get($data, (string) config('azari.payments.intouch.checkout_url_field'));
        $providerReference = (string) data_get(
            $data,
            (string) config('azari.payments.intouch.provider_reference_field')
        );

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new PaymentProviderException('InTouch did not return a valid checkout URL.', $this->name(), $response->status());
        }

        return [
            'checkout_url' => $url,
            'provider_reference' => $providerReference ?: null,
            'safe_response' => Arr::only($data, [
                config('azari.payments.intouch.provider_reference_field'),
                config('azari.payments.intouch.status_field'),
                'message',
            ]),
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();

        if ($providerReference === '') {
            throw new PaymentProviderException('InTouch transaction reference is missing.', $this->name());
        }

        $path = str_replace(
            '{reference}',
            rawurlencode($providerReference),
            (string) config('azari.payments.intouch.verify_path'),
        );

        $response = $this->request()
            ->retry(
                2,
                300,
                fn (\Throwable $exception) => $exception instanceof ConnectionException,
                throw: false,
            )
            ->get($path, [
                'reference' => $providerReference,
                'merchant_id' => config('azari.payments.intouch.merchant_id'),
            ]);

        $this->ensureSuccessful($response, 'Unable to verify InTouch payment.');
        $data = (array) ($response->json('data') ?? $response->json());

        $statusField = (string) config('azari.payments.intouch.status_field');
        $providerStatus = strtoupper((string) data_get($data, $statusField, 'PENDING'));
        $success = array_map('strtoupper', config('azari.payments.intouch.successful_statuses', []));
        $failed = array_map('strtoupper', config('azari.payments.intouch.failed_statuses', []));

        return [
            'status' => in_array($providerStatus, $success, true)
                ? 'successful'
                : (in_array($providerStatus, $failed, true) ? 'failed' : 'pending'),
            'provider_status' => $providerStatus,
            'provider_reference' => (string) data_get(
                $data,
                (string) config('azari.payments.intouch.provider_reference_field'),
                $providerReference,
            ),
            'merchant_reference' => (string) data_get(
                $data,
                (string) config('azari.payments.intouch.merchant_reference_field'),
                '',
            ),
            'amount' => (float) data_get($data, (string) config('azari.payments.intouch.amount_field'), 0),
            'currency' => strtoupper((string) data_get(
                $data,
                (string) config('azari.payments.intouch.currency_field'),
                '',
            )),
            'payment_method' => (string) ($data['payment_method'] ?? $data['channel'] ?? ''),
            'safe_response' => Arr::except($data, [
                'card', 'authorization', 'customer', 'token', 'secret', 'credentials',
                'password', 'api_key', 'apiKey', 'access_token',
            ]),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        $secret = (string) config('azari.payments.intouch.webhook_secret');
        if ($secret === '') return false;

        $signature = $this->header(
            $headers,
            (string) config('azari.payments.intouch.webhook_signature_header')
        );

        if ($signature === '') return false;

        $hex = hash_hmac('sha256', $rawPayload, $secret);
        $base64 = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

        return hash_equals($hex, trim($signature))
            || hash_equals($base64, trim($signature));
    }

    public function webhookReferences(array $payload): array
    {
        $data = (array) ($payload['data'] ?? $payload);

        $provider = (string) data_get(
            $data,
            (string) config('azari.payments.intouch.provider_reference_field'),
            '',
        );
        $merchant = (string) data_get(
            $data,
            (string) config('azari.payments.intouch.merchant_reference_field'),
            '',
        );

        return [
            'event_id' => (string) (
                $payload['event_id']
                ?? $payload['id']
                ?? hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES))
            ),
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

            if ($path === '') {
                return ['successful' => true, 'message' => 'Explicit merchant API profile is configured.'];
            }

            $response = $this->request()->get($path);

            return [
                'successful' => $response->successful(),
                'message' => $response->successful() ? 'Connection successful.' : 'Connection failed.',
            ];
        } catch (\Throwable) {
            return ['successful' => false, 'message' => 'Configuration is incomplete.'];
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('azari.payments.intouch.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('azari.integrations.http_timeout', 20))
            ->connectTimeout((int) config('azari.integrations.connect_timeout', 8));

        return match ((string) config('azari.payments.intouch.auth_mode')) {
            'token_header' => $request->withHeader(
                (string) config('azari.payments.intouch.token_header'),
                (string) config('azari.payments.intouch.api_token'),
            ),
            'basic' => $request->withBasicAuth(
                (string) config('azari.payments.intouch.username'),
                (string) config('azari.payments.intouch.password'),
            ),
            default => $request
                ->withBasicAuth(
                    (string) config('azari.payments.intouch.username'),
                    (string) config('azari.payments.intouch.password'),
                )
                ->withHeaders([
                    (string) config('azari.payments.intouch.merchant_header')
                        => (string) config('azari.payments.intouch.merchant_id'),
                    (string) config('azari.payments.intouch.secret_header')
                        => (string) config('azari.payments.intouch.secret'),
                ]),
        };
    }

    private function assertConfigured(): void
    {
        $profile = (string) config('azari.payments.intouch.profile');
        $base = (string) config('azari.payments.intouch.base_url');
        $authMode = (string) config('azari.payments.intouch.auth_mode');

        if (! $this->enabled()) {
            throw new PaymentProviderException('InTouch is disabled.', $this->name());
        }

        if ($profile !== 'custom_v1') {
            throw new PaymentProviderException(
                'InTouch is fail-closed until INTOUCH_API_PROFILE=custom_v1 is set after verifying the official merchant API contract.',
                $this->name()
            );
        }

        if (blank($base)
            || blank(config('azari.payments.intouch.merchant_id'))
            || blank(config('azari.payments.intouch.webhook_secret'))
            || blank(config('azari.payments.intouch.callback_url'))
        ) {
            throw new PaymentProviderException('InTouch is not completely configured.', $this->name());
        }

        if ($authMode === 'token_header' && blank(config('azari.payments.intouch.api_token'))) {
            throw new PaymentProviderException('InTouch API token is missing.', $this->name());
        }

        if ($authMode !== 'token_header'
            && (blank(config('azari.payments.intouch.username'))
                || blank(config('azari.payments.intouch.password')))
        ) {
            throw new PaymentProviderException('InTouch basic-auth credentials are missing.', $this->name());
        }

        if (app()->environment('production')
            && config('azari.integrations.require_https_in_production')
            && ! str_starts_with(strtolower($base), 'https://')
        ) {
            throw new PaymentProviderException('InTouch must use HTTPS in production.', $this->name());
        }
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful()) {
            throw new PaymentProviderException(
                $message,
                $this->name(),
                $response->status(),
                ['message' => $response->json('message')]
            );
        }
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
