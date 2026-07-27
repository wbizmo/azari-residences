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
            && filled(config('services.twilio.token'));
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
            throw new RuntimeException('Twilio SMS is not configured.');
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
            throw new RuntimeException('Twilio WhatsApp is not configured.');
        }

        $payload = [
            'To' => $this->numbers->whatsapp($recipient),
            'From' => $this->whatsappAddress((string) config('services.twilio.whatsapp_from')),
        ];

        if (filled($options['content_sid'] ?? null)) {
            $payload['ContentSid'] = $options['content_sid'];
            if (! empty($options['content_variables'])) {
                $payload['ContentVariables'] = json_encode($options['content_variables'], JSON_THROW_ON_ERROR);
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
            $payload['StatusCallback'] = $callback;
        }

        $response = $this->client()->asForm()->post(
            "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
            $payload,
        );

        if (! $response->successful()) {
            throw new RuntimeException('Twilio rejected the message: '.($response->json('message') ?: 'delivery failed'));
        }

        return (array) $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('services.twilio.sid'),
            (string) config('services.twilio.token'),
        )->acceptJson()->timeout(20)->retry(2, 250, throw: false);
    }

    private function whatsappAddress(string $value): string
    {
        return str_starts_with($value, 'whatsapp:') ? $value : 'whatsapp:'.$this->numbers->normalize($value);
    }
}
