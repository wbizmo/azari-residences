<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class PesapalService implements PaymentProvider
{
    public function name(): string { return 'pesapal'; }
    public function enabled(): bool { return (bool) config('azari.payments.pesapal.enabled'); }
    public function mode(): string { return (string) config('azari.payments.pesapal.mode', 'sandbox'); }

    public function initialise(array $payload): array
    {
        $this->assertConfigured();

        // SubmitOrderRequest is not automatically retried because the first
        // request may have reached Pesapal even when the response was lost.
        $response = $this->request()
            ->withToken($this->token())
            ->post((string) config('azari.payments.pesapal.submit_order_path', '/api/Transactions/SubmitOrderRequest'), [
                'id' => $payload['reference'],
                'currency' => strtoupper($payload['currency']),
                'amount' => round((float) $payload['amount'], 2),
                'description' => 'Azari booking '.$payload['booking_reference'],
                'callback_url' => $payload['callback_url'] ?: config('azari.payments.pesapal.callback_url'),
                'notification_id' => config('azari.payments.pesapal.notification_id'),
                'billing_address' => [
                    'email_address' => $payload['email'],
                    'phone_number' => $payload['phone'] ?? '',
                    'first_name' => $payload['first_name'] ?? $payload['name'],
                    'middle_name' => '',
                    'last_name' => $payload['last_name'] ?? '',
                    'line_1' => '',
                    'line_2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'zip_code' => '',
                    'country_code' => $payload['country_code'] ?? '',
                ],
            ]);

        $this->ensureSuccessful($response, 'Unable to initialise Pesapal checkout.');

        $url = (string) $response->json('redirect_url', '');
        $tracking = (string) $response->json('order_tracking_id', '');

        if ($url === '' || $tracking === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new PaymentProviderException(
                'Pesapal did not return a complete checkout response.',
                $this->name(),
                $response->status()
            );
        }

        return [
            'checkout_url' => $url,
            'provider_reference' => $tracking,
            'safe_response' => Arr::only((array) $response->json(), [
                'order_tracking_id', 'merchant_reference', 'status', 'error',
            ]),
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();

        if ($providerReference === '') {
            throw new PaymentProviderException('Pesapal OrderTrackingId is missing.', $this->name());
        }

        $response = $this->safeGet(
            (string) config('azari.payments.pesapal.status_path', '/api/Transactions/GetTransactionStatus'),
            ['orderTrackingId' => $providerReference],
        );

        $this->ensureSuccessful($response, 'Unable to verify Pesapal payment.');
        $data = (array) $response->json();
        $status = strtoupper((string) ($data['payment_status_description'] ?? 'PENDING'));

        return [
            'status' => $status === 'COMPLETED'
                ? 'successful'
                : (in_array($status, ['FAILED', 'INVALID', 'REVERSED'], true) ? 'failed' : 'pending'),
            'provider_status' => $status,
            'provider_reference' => $providerReference,
            'merchant_reference' => (string) ($data['merchant_reference'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'payment_method' => (string) ($data['payment_method'] ?? ''),
            'paid_at' => $data['created_date'] ?? null,
            'safe_response' => Arr::only($data, [
                'payment_status_description', 'status_code', 'merchant_reference',
                'amount', 'currency', 'payment_method', 'confirmation_code',
                'created_date', 'description', 'message',
            ]),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        // Pesapal API 3.0 IPN does not provide an HMAC signature. The IPN is
        // treated only as a notification pointer. Authentication is completed
        // by querying GetTransactionStatus with Azari's bearer token and then
        // matching merchant reference, amount and currency in PaymentFinalizer.
        return $this->enabled()
            && filled(config('azari.payments.pesapal.consumer_key'))
            && filled(config('azari.payments.pesapal.consumer_secret'))
            && filled(config('azari.payments.pesapal.notification_id'));
    }

    public function webhookReferences(array $payload): array
    {
        $tracking = (string) ($payload['OrderTrackingId'] ?? $payload['orderTrackingId'] ?? '');
        $merchant = (string) ($payload['OrderMerchantReference'] ?? $payload['orderMerchantReference'] ?? '');
        $type = (string) ($payload['OrderNotificationType'] ?? $payload['orderNotificationType'] ?? 'IPNCHANGE');

        return [
            'event_id' => hash('sha256', $tracking.'|'.$merchant.'|'.$type),
            'event_type' => $type,
            'merchant_reference' => $merchant,
            'provider_reference' => $tracking,
        ];
    }

    public function healthCheck(): array
    {
        if (! $this->enabled()) return ['successful' => false, 'message' => 'Provider is disabled.'];

        try {
            $this->token(true);
            return ['successful' => true, 'message' => 'Authentication successful.'];
        } catch (\Throwable) {
            return ['successful' => false, 'message' => 'Authentication failed.'];
        }
    }

    private function token(bool $refresh = false): string
    {
        $key = 'azari:pesapal:token:'.$this->mode();
        if ($refresh) Cache::forget($key);

        return Cache::remember(
            $key,
            now()->addSeconds((int) config('azari.payments.pesapal.token_cache_seconds', 240)),
            function (): string {
                $response = $this->request()->post(
                    (string) config('azari.payments.pesapal.auth_path', '/api/Auth/RequestToken'),
                    [
                        'consumer_key' => config('azari.payments.pesapal.consumer_key'),
                        'consumer_secret' => config('azari.payments.pesapal.consumer_secret'),
                    ],
                );

                $this->ensureSuccessful($response, 'Unable to authenticate with Pesapal.');
                $token = (string) $response->json('token', '');

                if ($token === '') {
                    throw new PaymentProviderException('Pesapal did not return an access token.', $this->name());
                }

                return $token;
            }
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('azari.payments.pesapal.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('azari.integrations.http_timeout', 20))
            ->connectTimeout((int) config('azari.integrations.connect_timeout', 8));
    }

    private function safeGet(string $path, array $query): Response
    {
        return $this->request()
            ->withToken($this->token())
            ->retry(
                2,
                300,
                fn (\Throwable $exception) => $exception instanceof ConnectionException,
                throw: false,
            )
            ->get($path, $query);
    }

    private function assertConfigured(): void
    {
        $base = (string) config('azari.payments.pesapal.base_url');

        if (! $this->enabled()
            || blank(config('azari.payments.pesapal.consumer_key'))
            || blank(config('azari.payments.pesapal.consumer_secret'))
            || blank(config('azari.payments.pesapal.notification_id'))
        ) {
            throw new PaymentProviderException('Pesapal is not completely configured.', $this->name());
        }

        if (app()->environment('production')
            && config('azari.integrations.require_https_in_production')
            && ! str_starts_with(strtolower($base), 'https://')
        ) {
            throw new PaymentProviderException('Pesapal must use HTTPS in production.', $this->name());
        }
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        $error = $response->json('error');

        if (! $response->successful()
            || (is_array($error) && array_filter($error) !== [])
            || (is_string($error) && $error !== '')
        ) {
            $providerMessage = is_array($error)
                ? (string) ($error['message'] ?? $error['code'] ?? '')
                : (is_string($error) ? $error : '');

            throw new PaymentProviderException(
                $providerMessage !== '' ? $message.' Pesapal: '.$providerMessage : $message,
                $this->name(),
                $response->status(),
                [
                    'error' => $error,
                    'message' => $response->json('message'),
                ]
            );
        }
    }
}
