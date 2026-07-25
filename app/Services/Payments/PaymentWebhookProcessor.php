<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentProviderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class PaymentWebhookProcessor
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly PaymentFinalizer $finalizer,
    ) {}

    public function process(string $providerName, Request $request): array
    {
        $provider = $this->manager->driver($providerName);
        $raw = $request->getContent();
        $payload = $request->all();
        if ($payload === [] && $raw !== '') $payload = json_decode($raw, true) ?: [];
        $references = $provider->webhookReferences($payload);
        $signatureValid = $provider->webhookSignatureIsValid($raw, $request->headers->all());
        $eventId = (string) ($references['event_id'] ?? hash('sha256', $raw));

        $event = PaymentEvent::query()->firstOrCreate(
            ['provider' => $providerName, 'event_id' => $eventId],
            [
                'event_type' => $references['event_type'] ?? null,
                'source' => 'webhook',
                'signature_valid' => $signatureValid,
                'processed' => false,
                'received_at' => now(),
                'safe_payload' => $this->safePayload($payload),
            ],
        );

        PaymentProviderStatus::query()->updateOrCreate(
            ['provider' => $providerName],
            ['enabled' => $provider->enabled(), 'mode' => $provider->mode(), 'last_webhook_at' => now()],
        );

        if (! $signatureValid) {
            if ($event->wasRecentlyCreated) {
                $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => 'Invalid webhook signature.']);
                AuditLog::record('payment.webhook_rejected', $event, [], [], ['provider' => $providerName]);
            }
            return ['duplicate' => ! $event->wasRecentlyCreated, 'processed' => false, 'invalid_signature' => true];
        }

        if (blank($references['merchant_reference'] ?? null) && blank($references['provider_reference'] ?? null)) {
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => 'Payment references are missing.']);
            return ['duplicate' => ! $event->wasRecentlyCreated, 'processed' => false, 'malformed' => true];
        }

        $payment = Payment::query()
            ->when(filled($references['merchant_reference'] ?? null), fn ($q) => $q->where('reference', $references['merchant_reference']))
            ->when(blank($references['merchant_reference'] ?? null) && filled($references['provider_reference'] ?? null), fn ($q) => $q->where('provider_reference', $references['provider_reference']))
            ->first();

        if (! $payment) {
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => 'Payment record not found.']);
            return ['duplicate' => ! $event->wasRecentlyCreated, 'processed' => false, 'missing_payment' => true];
        }

        $event->update(['payment_id' => $payment->id]);
        try {
            // Duplicate delivery is acknowledged but safely re-queried while the payment
            // remains unresolved. The unique event row prevents duplicate history records.
            if (! $event->wasRecentlyCreated && in_array($payment->status, [Payment::SUCCESSFUL, 'successful_excess'], true)) {
                return ['duplicate' => true, 'processed' => true];
            }

            $providerReference = (string) ($references['provider_reference'] ?? $payment->provider_reference ?? '');
            $verification = $provider->verify($providerReference);
            $this->finalizer->apply($payment, $verification, 'webhook');
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => null]);
            return ['duplicate' => ! $event->wasRecentlyCreated, 'processed' => true];
        } catch (\Throwable $e) {
            Log::error('Payment webhook processing failed.', [
                'provider' => $providerName,
                'payment_reference' => $payment->reference,
                'event_id' => $eventId,
                'exception' => $e,
            ]);
            $event->update(['processed' => true, 'processed_at' => now(), 'safe_error' => 'Webhook verification failed.']);
            return ['duplicate' => ! $event->wasRecentlyCreated, 'processed' => false, 'error' => true];
        }
    }

    private function safePayload(array $payload): array
    {
        return Arr::except($payload, [
            'card', 'authorization', 'customer', 'token', 'secret', 'credentials',
            'password', 'api_key', 'apiKey', 'access_token',
        ]);
    }
}
