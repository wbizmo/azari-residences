<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
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
        $response = $this->request()->withToken($this->token())->post('/api/Transactions/SubmitOrderRequest', [
            'id' => $payload['reference'],
            'currency' => strtoupper($payload['currency']),
            'amount' => round((float) $payload['amount'], 2),
            'description' => 'Azari booking '.$payload['booking_reference'],
            'callback_url' => config('azari.payments.pesapal.callback_url') ?: $payload['callback_url'],
            'notification_id' => config('azari.payments.pesapal.notification_id'),
            'billing_address' => [
                'email_address' => $payload['email'],
                'phone_number' => $payload['phone'] ?? '',
                'first_name' => $payload['first_name'] ?? $payload['name'],
                'last_name' => $payload['last_name'] ?? '',
                'country_code' => $payload['country_code'] ?? '',
            ],
        ]);
        $this->ensureSuccessful($response, 'Unable to initialise Pesapal checkout.');

        $url = (string) $response->json('redirect_url', '');
        if ($url === '') throw new PaymentProviderException('Pesapal did not return a checkout URL.', $this->name(), $response->status());

        return [
            'checkout_url' => $url,
            'provider_reference' => (string) $response->json('order_tracking_id', ''),
            'safe_response' => Arr::only((array) $response->json(), ['order_tracking_id', 'merchant_reference', 'status', 'error']),
        ];
    }

    public function verify(string $providerReference): array
    {
        $this->assertConfigured();
        $response = $this->request()->withToken($this->token())->get('/api/Transactions/GetTransactionStatus', [
            'orderTrackingId' => $providerReference,
        ]);
        $this->ensureSuccessful($response, 'Unable to verify Pesapal payment.');
        $data = (array) $response->json();
        $status = strtoupper((string) ($data['payment_status_description'] ?? 'PENDING'));

        return [
            'status' => $status === 'COMPLETED' ? 'successful' : (in_array($status, ['FAILED', 'INVALID', 'REVERSED'], true) ? 'failed' : 'pending'),
            'provider_status' => $status,
            'provider_reference' => $providerReference,
            'merchant_reference' => (string) ($data['merchant_reference'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'payment_method' => (string) ($data['payment_method'] ?? ''),
            'safe_response' => Arr::only($data, ['payment_status_description', 'merchant_reference', 'amount', 'currency', 'payment_method', 'confirmation_code', 'created_date']),
        ];
    }

    public function webhookSignatureIsValid(string $rawPayload, array $headers): bool
    {
        // Pesapal API 3 IPN does not carry a payment result. Authenticity is established
        // by re-querying GetTransactionStatus with the merchant token and matching references.
        return $this->enabled() && filled(config('azari.payments.pesapal.consumer_key'));
    }

    public function webhookReferences(array $payload): array
    {
        $tracking = (string) ($payload['OrderTrackingId'] ?? $payload['orderTrackingId'] ?? '');
        $merchant = (string) ($payload['OrderMerchantReference'] ?? $payload['orderMerchantReference'] ?? '');
        return [
            'event_id' => hash('sha256', $tracking.'|'.$merchant.'|'.($payload['OrderNotificationType'] ?? 'IPN')),
            'event_type' => (string) ($payload['OrderNotificationType'] ?? 'IPNCHANGE'),
            'merchant_reference' => $merchant,
            'provider_reference' => $tracking,
        ];
    }

    public function healthCheck(): array
    {
        if (! $this->enabled()) return ['successful' => false, 'message' => 'Provider is disabled.'];
        try {
            $this->token(true);
            return ['successful' => true, 'message' => 'Connection successful.'];
        } catch (\Throwable $e) {
            return ['successful' => false, 'message' => 'Connection failed.'];
        }
    }

    private function token(bool $refresh = false): string
    {
        $key = 'azari:pesapal:token:'.$this->mode();
        if ($refresh) Cache::forget($key);

        return Cache::remember($key, now()->addMinutes(4), function (): string {
            $response = $this->request()->post('/api/Auth/RequestToken', [
                'consumer_key' => config('azari.payments.pesapal.consumer_key'),
                'consumer_secret' => config('azari.payments.pesapal.consumer_secret'),
            ]);
            $this->ensureSuccessful($response, 'Unable to authenticate with Pesapal.');
            $token = (string) $response->json('token', '');
            if ($token === '') throw new PaymentProviderException('Pesapal did not return an access token.', $this->name());
            return $token;
        });
    }

    private function request()
    {
        return Http::baseUrl((string) config('azari.payments.pesapal.base_url'))
            ->acceptJson()->asJson()->timeout(20)->connectTimeout(8)->retry(2, 300, throw: false);
    }

    private function assertConfigured(): void
    {
        if (! $this->enabled() || blank(config('azari.payments.pesapal.consumer_key')) || blank(config('azari.payments.pesapal.consumer_secret')) || blank(config('azari.payments.pesapal.notification_id'))) {
            throw new PaymentProviderException('Pesapal is not configured.', $this->name());
        }
    }

    private function ensureSuccessful(Response $response, string $message): void
    {
        if (! $response->successful() || filled($response->json('error'))) {
            throw new PaymentProviderException($message, $this->name(), $response->status(), ['error' => $response->json('error')]);
        }
    }
}
