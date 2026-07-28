<?php

namespace App\Services\Communication;

use App\Contracts\Communication\SmsProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TwilioSmsService implements SmsProvider
{
    public function __construct(private readonly PhoneNumberNormalizer $numbers) {}

    public function enabled(): bool
    {
        return (bool) config('services.twilio.enabled')
            && filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && (
                filled(config('services.twilio.messaging_service_sid'))
                || filled(config('services.twilio.from'))
            );
    }

    public function whatsappEnabled(): bool
    {
        return $this->enabled()
            && (bool) config('services.twilio.whatsapp_enabled')
            && filled(config('services.twilio.whatsapp_from'));
    }

    public function send(string $recipient, string $message, array $options = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Twilio SMS is not completely configured.');
        }

        $payload = [
            'To' => $this->numbers->normalize($recipient),
            'Body' => mb_substr(trim($message), 0, 1500),
        ];

        $messagingService = config('services.twilio.messaging_service_sid');

        if (filled($messagingService)) {
            $payload['MessagingServiceSid'] = $messagingService;
        } else {
            $payload['From'] = config('services.twilio.from');
        }

        return $this->dispatch($payload, $options);
    }

    public function sendWhatsApp(string $recipient, string $message, array $options = []): array
    {
        if (! $this->whatsappEnabled()) {
            throw new RuntimeException('Twilio WhatsApp is not completely configured.');
        }

        $payload = [
            'To' => $this->numbers->whatsapp($recipient),
            'From' => $this->whatsappAddress((string) config('services.twilio.whatsapp_from')),
        ];

        if (filled($options['content_sid'] ?? null)) {
            $payload['ContentSid'] = $options['content_sid'];

            if (! empty($options['content_variables'])) {
                $payload['ContentVariables'] = json_encode(
                    $options['content_variables'],
                    JSON_THROW_ON_ERROR
                );
            }
        } else {
            $payload['Body'] = mb_substr(trim($message), 0, 1500);
        }

        return $this->dispatch($payload, $options);
    }

    private function dispatch(array $payload, array $options): array
    {
        $sid = (string) config('services.twilio.sid');
        $callback = $options['status_callback'] ?? config('services.twilio.status_callback');

        if (filled($callback)) {
            if (! filter_var($callback, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Twilio StatusCallback must be a fully qualified URL.');
            }

            $payload['StatusCallback'] = $callback;
        }

        // Message creation is intentionally not retried automatically. A
        // timeout may occur after Twilio accepted the message, and retrying
        // could send the customer a duplicate notification.
        $response = $this->client()->asForm()->post($this->messagesEndpoint($sid), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Twilio rejected the message: '.($response->json('message') ?: 'delivery failed')
            );
        }

        $result = (array) $response->json();

        if (blank($result['sid'] ?? null)) {
            throw new RuntimeException('Twilio accepted the request without returning a Message SID.');
        }

        return $result;
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('services.twilio.sid'),
            (string) config('services.twilio.token'),
        )
            ->acceptJson()
            ->timeout((int) config('azari.integrations.http_timeout', 20))
            ->connectTimeout((int) config('azari.integrations.connect_timeout', 8));
    }

    private function messagesEndpoint(string $sid): string
    {
        $base = rtrim((string) config('services.twilio.api_base_url'), '/');
        $version = trim((string) config('services.twilio.api_version'), '/');

        if (app()->environment('production')
            && config('azari.integrations.require_https_in_production')
            && ! str_starts_with(strtolower($base), 'https://')
        ) {
            throw new RuntimeException('Twilio must use HTTPS in production.');
        }

        return "{$base}/{$version}/Accounts/{$sid}/Messages.json";
    }

    private function whatsappAddress(string $value): string
    {
        return str_starts_with($value, 'whatsapp:')
            ? $value
            : 'whatsapp:'.$this->numbers->normalize($value);
    }
}
