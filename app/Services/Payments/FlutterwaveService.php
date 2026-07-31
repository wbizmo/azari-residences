<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class FlutterwaveService implements PaymentProvider
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function enabled(): bool
    {
        return (bool) config('azari.payments.flutterwave.enabled');
    }

    public function mode(): string
    {
        return (string) config('azari.payments.flutterwave.mode', 'test');
    }

    public function initialise(array $payload): array
    {
        $this->assertConfigured();

        $options = (array) ($payload['provider_options'] ?? []);
        $method = strtolower((string) ($options['flutterwave_payment_method'] ?? $this->defaultPaymentMethod()));

        if (! in_array($method, $this->allowedPaymentMethods(), true)) {
            throw new PaymentProviderException('The selected Flutterwave payment method is unavailable.', $this->name());
        }

        $currency = strtoupper((string) $payload['currency']);
        if (in_array($method, ['opay', 'ussd'], true) && $currency !== 'NGN') {
            throw new PaymentProviderException(
                'The selected Flutterwave v4 payment method currently supports NGN bookings only.',
                $this->name()
            );
        }

        $paymentMethod = ['type' => $method];
        if ($method === 'ussd') {
            $bankCode = trim((string) ($options['flutterwave_ussd_bank'] ?? ''));
            if ($bankCode === '' || ! preg_match('/^[A-Za-z0-9_-]{2,20}$/', $bankCode)) {
                throw new PaymentProviderException('Select a valid bank for Flutterwave USSD.', $this->name());
            }

            $paymentMethod['ussd'] = ['account_bank' => $bankCode];
        }

        [$countryCode, $phoneNumber] = $this->phoneParts((string) ($payload['phone'] ?? ''));

        $customer = [
            'email' => (string) $payload['email'],
            'name' => [
                'first' => (string) ($payload['first_name'] ?? ''),
                'middle' => '',
                'last' => (string) ($payload['last_name'] ?? ''),
            ],
        ];

        if ($phoneNumber !== '') {
            $customer['phone'] = [
                'country_code' => $countryCode,
                'number' => $phoneNumber,
            ];
        }

        $request = [
            'amount' => round((float) $payload['amount'], 2),
            'currency' => $currency,
            'reference' => (string) $payload['reference'],
            'payment_method' => $paymentMethod,
            'redirect_url' => (string) $payload['callback_url'],
            'customer' => $customer,
            'meta' => [
                'booking_reference' => (string) $payload['booking_reference'],
                'payment_reference' => (string) $payload['reference'],
                'application' => 'azari-residences',
            ],
        ];

        $reference = (string) $payload['reference'];
        $traceId = $this->requestId('trace', $reference);
        $idempotencyKey = $this->requestId('init', $reference);

        // Checkout creation is never retried for connection failures. A retry
        // occurs only after an HTTP 401, using the same idempotency key, because
        // an unauthorized request cannot have created a charge.
        $response = $this->postAuthorized(
            (string) config('azari.payments.flutterwave.orchestrator_path'),
            $request,
            $traceId,
            $idempotencyKey,
        );

        $this->ensureSuccessful($response, 'Unable to initialise Flutterwave v4 payment.');

        $data = (array) $response->json('data', []);
        $providerReference = (string) ($data['id'] ?? '');
        if ($providerReference === '') {
            throw new PaymentProviderException(
                'Flutterwave did not return a charge reference.',
                $this->name(),
                $response->status()
            );
        }

        $nextActionType = strtolower((string) data_get($data, 'next_action.type', ''));
        $redirectUrl = (string) data_get($data, 'next_action.redirect_url.url', '');
        $instruction = trim((string) data_get($data, 'next_action.payment_instruction.note', ''));
        $providerStatus = strtolower((string) ($data['status'] ?? 'pending'));

        $checkoutUrl = $redirectUrl;
        if ($checkoutUrl === '' && $instruction !== '') {
            $checkoutUrl = (string) ($payload['instructions_url'] ?? '');
        }

        if ($checkoutUrl === '' && $providerStatus === 'succeeded') {
            $checkoutUrl = $this->appendQuery((string) $payload['callback_url'], [
                'charge_id' => $providerReference,
                'status' => 'succeeded',
            ]);
        }

        if ($checkoutUrl === '' || ! filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
            throw new PaymentProviderException(
                'Flutterwave requires an unsupported authorization step. Choose another payment method.',
                $this->name(),
                $response->status(),
                ['next_action_type' => $nextActionType]
            );
        }

        return [
            'checkout_url' => $checkoutUrl,
            'provider_reference' => $providerReference,
            'safe_response' => [
                'status' => $response->json('status'),
                'message' => $response->json('message'),
                'charge_id' => $providerReference,
                'provider_status' => $providerStatus,
                'payment_method' => $method,
                'next_action_type' => $nextActionType,
                'payment_instruction' => $instruction !== '' ? $instruction : null,
            ],
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();

        if ($providerReference === '') {
            throw new PaymentProviderException('Flutterwave charge ID is missing.', $this->name());
        }

        $path = str_replace(
            '{id}',
            rawurlencode($providerReference),
            (string) config('azari.payments.flutterwave.charge_path', '/charges/{id}'),
        );

        $response = $this->getAuthorized(
            $path,
            [],
            $this->requestId('verify', $providerReference),
        );
        $this->ensureSuccessful($response, 'Unable to verify Flutterwave v4 payment.');

        $data = (array) $response->json('data', []);
        $status = strtolower((string) ($data['status'] ?? 'pending'));

        return [
            'status' => $status === 'succeeded'
                ? 'successful'
                : (in_array($status, ['failed', 'voided', 'cancelled', 'canceled'], true) ? 'failed' : 'pending'),
            'provider_status' => $status,
            'provider_reference' => (string) ($data['id'] ?? $providerReference),
            'merchant_reference' => (string) ($data['reference'] ?? ''),
            'amount' => (float) ($data['amount'] ?? $data['charged_amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'payment_method' => (string) (
                data_get($data, 'payment_method_details.type')
                ?: data_get($data, 'payment_method.type')
                ?: ''
            ),
            'paid_at' => $status === 'succeeded' ? ($data['created_datetime'] ?? null) : null,
            'safe_response' => Arr::only($data, [
                'id',
                'reference',
                'status',
                'amount',
                'charged_amount',
                'currency',
                'created_datetime',
                'processor_response',
            ]),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        $secret = (string) config('azari.payments.flutterwave.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $signature = $this->header($headers, 'flutterwave-signature');
        if ($signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

        return hash_equals($expected, trim($signature));
    }

    public function webhookReferences(array $payload): array
    {
        $data = (array) ($payload['data'] ?? []);

        return [
            'event_id' => (string) (
                $payload['webhook_id']
                ?? $payload['id']
                ?? hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES))
            ),
            'event_type' => (string) ($payload['type'] ?? 'charge.webhook'),
            'merchant_reference' => (string) (
                $data['reference']
                ?? $payload['reference']
                ?? ''
            ),
            'provider_reference' => (string) (
                $data['id']
                ?? $payload['charge_id']
                ?? ''
            ),
        ];
    }

    public function healthCheck(): array
    {
        if (! $this->enabled()) {
            return ['successful' => false, 'message' => 'Provider is disabled.'];
        }

        try {
            $this->assertConfigured();

            return ['successful' => true, 'message' => 'Flutterwave API v4 configuration is complete.'];
        } catch (\Throwable) {
            return ['successful' => false, 'message' => 'Configuration is incomplete.'];
        }
    }

    public function allowedPaymentMethods(): array
    {
        $configured = (array) config('azari.payments.flutterwave.allowed_payment_methods', ['opay', 'ussd']);

        return array_values(array_intersect(
            ['opay', 'ussd'],
            array_values(array_unique(array_map(
                static fn (mixed $method): string => strtolower(trim((string) $method)),
                $configured
            )))
        ));
    }

    public function defaultPaymentMethod(): string
    {
        $default = strtolower((string) config('azari.payments.flutterwave.default_payment_method', 'opay'));

        return in_array($default, $this->allowedPaymentMethods(), true)
            ? $default
            : ($this->allowedPaymentMethods()[0] ?? 'opay');
    }

    public function supportedBanks(string $country = 'NG'): array
    {
        $this->assertConfigured();
        $country = strtoupper(trim($country));

        return Cache::remember(
            'azari:flutterwave:v4:banks:'.$this->mode().':'.$country,
            now()->addHours(6),
            function () use ($country): array {
                $response = $this->getAuthorized(
                    (string) config('azari.payments.flutterwave.banks_path', '/banks'),
                    ['country' => $country],
                    $this->requestId('banks', $country),
                );
                $this->ensureSuccessful($response, 'Unable to retrieve Flutterwave banks.');

                return collect((array) $response->json('data', []))
                    ->map(static fn (mixed $bank): array => [
                        'code' => trim((string) data_get($bank, 'code', '')),
                        'name' => trim((string) data_get($bank, 'name', '')),
                    ])
                    ->filter(static fn (array $bank): bool => $bank['code'] !== '' && $bank['name'] !== '')
                    ->unique('code')
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            }
        );
    }

    private function postAuthorized(
        string $path,
        array $body,
        string $traceId,
        string $idempotencyKey
    ): Response {
        $response = $this->authorizedRequest($traceId, $idempotencyKey)->post($path, $body);

        if ($response->unauthorized()) {
            $this->forgetAccessToken();
            $response = $this->authorizedRequest($traceId, $idempotencyKey)->post($path, $body);
        }

        return $response;
    }

    private function getAuthorized(string $path, array $query, string $traceId): Response
    {
        $response = $this->authorizedRequest($traceId)
            ->retry(
                2,
                300,
                static fn (\Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            )
            ->get($path, $query);

        if ($response->unauthorized()) {
            $this->forgetAccessToken();
            $response = $this->authorizedRequest($traceId)
                ->retry(
                    2,
                    300,
                    static fn (\Throwable $exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->get($path, $query);
        }

        return $response;
    }

    private function authorizedRequest(string $traceId, ?string $idempotencyKey = null): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Trace-Id' => $traceId])
            ->timeout((int) config('azari.integrations.http_timeout', 20))
            ->connectTimeout((int) config('azari.integrations.connect_timeout', 8));

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        return $request;
    }

    private function accessToken(): string
    {
        $cacheKey = $this->accessTokenCacheKey();

        return (string) Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('azari.payments.flutterwave.token_cache_seconds', 540)),
            function (): string {
                $response = Http::asForm()
                    ->acceptJson()
                    ->timeout((int) config('azari.integrations.http_timeout', 20))
                    ->connectTimeout((int) config('azari.integrations.connect_timeout', 8))
                    ->post((string) config('azari.payments.flutterwave.token_url'), [
                        'client_id' => (string) config('azari.payments.flutterwave.client_id'),
                        'client_secret' => (string) config('azari.payments.flutterwave.client_secret'),
                        'grant_type' => 'client_credentials',
                    ]);

                if (! $response->successful() || blank($response->json('access_token'))) {
                    throw new PaymentProviderException(
                        'Flutterwave authentication failed.',
                        $this->name(),
                        $response->status(),
                        ['error' => $response->json('error')]
                    );
                }

                return (string) $response->json('access_token');
            }
        );
    }

    private function forgetAccessToken(): void
    {
        Cache::forget($this->accessTokenCacheKey());
    }

    private function accessTokenCacheKey(): string
    {
        return 'azari:flutterwave:v4:token:'.hash('sha256', implode('|', [
            $this->mode(),
            (string) config('azari.payments.flutterwave.client_id'),
        ]));
    }

    private function baseUrl(): string
    {
        $key = $this->mode() === 'live' ? 'live_base_url' : 'sandbox_base_url';

        return rtrim((string) config('azari.payments.flutterwave.'.$key), '/');
    }

    private function assertConfigured(): void
    {
        $baseUrl = $this->baseUrl();
        $tokenUrl = (string) config('azari.payments.flutterwave.token_url');

        if (! $this->enabled()
            || config('azari.payments.flutterwave.api_version') !== 'v4'
            || blank(config('azari.payments.flutterwave.client_id'))
            || blank(config('azari.payments.flutterwave.client_secret'))
            || blank(config('azari.payments.flutterwave.webhook_secret'))
            || $baseUrl === ''
            || $tokenUrl === ''
        ) {
            throw new PaymentProviderException('Flutterwave API v4 is not completely configured.', $this->name());
        }

        $this->assertSecureUrl($baseUrl);
        $this->assertSecureUrl($tokenUrl);
    }

    private function assertSecureUrl(string $url): void
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
        $status = strtolower((string) $response->json('status'));

        if (! $response->successful() || in_array($status, ['error', 'failed'], true)) {
            throw new PaymentProviderException(
                $message,
                $this->name(),
                $response->status(),
                [
                    'message' => $response->json('message') ?: $response->json('error.message'),
                    'code' => $response->json('error.code'),
                    'type' => $response->json('error.type'),
                ]
            );
        }
    }

    private function requestId(string $purpose, string $reference): string
    {
        return 'azari-flw-'.$purpose.'-'.substr(hash('sha256', $reference), 0, 32);
    }

    private function phoneParts(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return ['', ''];
        }

        if (str_starts_with($digits, '234') && strlen($digits) > 10) {
            return ['234', substr($digits, 3)];
        }

        if (str_starts_with($digits, '0')) {
            return ['234', ltrim($digits, '0')];
        }

        return ['234', $digits];
    }

    private function appendQuery(string $url, array $query): string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
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
