<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentProviderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        if ($payload === [] && $raw !== '') {
            $payload = json_decode($raw, true) ?: [];
        }

        $references = $provider->webhookReferences($payload);
        $signatureValid = $provider->webhookSignatureIsValid($raw, $request->headers->all());
        $eventId = (string) ($references['event_id'] ?? hash('sha256', $raw));

        $event = DB::transaction(function () use ($providerName, $eventId, $references, $signatureValid, $payload): PaymentEvent {
            $event = PaymentEvent::query()->firstOrCreate(
                ['provider' => $providerName, 'event_id' => $eventId],
                [
                    'event_type' => $references['event_type'] ?? null,
                    'source' => 'webhook',
                    'signature_valid' => $signatureValid,
                    'processed' => false,
                    'received_at' => now(),
                    'safe_payload' => $this->safePayload($payload),
                    'attempt_count' => 0,
                ],
            );

            return PaymentEvent::query()
                ->whereKey($event->id)
                ->when(
                    DB::connection()->getDriverName() !== 'sqlite',
                    fn ($query) => $query->lockForUpdate()
                )
                ->firstOrFail();
        }, 3);

        PaymentProviderStatus::query()->updateOrCreate(
            ['provider' => $providerName],
            [
                'enabled' => $provider->enabled(),
                'mode' => $provider->mode(),
                'last_webhook_at' => now(),
            ],
        );

        if (! $signatureValid) {
            if ($event->wasRecentlyCreated) {
                $event->update([
                    'processed' => true,
                    'processed_at' => now(),
                    'safe_error' => 'Invalid webhook signature or provider authentication.',
                ]);
                AuditLog::record('payment.webhook_rejected', $event, [], [], [
                    'provider' => $providerName,
                ]);
            }

            return [
                'duplicate' => ! $event->wasRecentlyCreated,
                'processed' => false,
                'invalid_signature' => true,
            ];
        }

        if (blank($references['merchant_reference'] ?? null)
            && blank($references['provider_reference'] ?? null)
        ) {
            $event->update([
                'processed' => true,
                'processed_at' => now(),
                'safe_error' => 'Payment references are missing.',
            ]);

            return [
                'duplicate' => ! $event->wasRecentlyCreated,
                'processed' => false,
                'malformed' => true,
            ];
        }

        $payment = Payment::query()
            ->when(
                filled($references['merchant_reference'] ?? null),
                fn ($query) => $query->where('reference', $references['merchant_reference'])
            )
            ->when(
                blank($references['merchant_reference'] ?? null)
                    && filled($references['provider_reference'] ?? null),
                fn ($query) => $query->where('provider_reference', $references['provider_reference'])
            )
            ->first();

        if (! $payment) {
            $event->update([
                'processed' => true,
                'processed_at' => now(),
                'safe_error' => 'Payment record not found.',
            ]);

            return [
                'duplicate' => ! $event->wasRecentlyCreated,
                'processed' => false,
                'missing_payment' => true,
            ];
        }

        $event->update([
            'payment_id' => $payment->id,
            'attempt_count' => ((int) $event->attempt_count) + 1,
        ]);

        try {
            if (! $event->wasRecentlyCreated
                && in_array($payment->status, [Payment::SUCCESSFUL, 'successful_excess'], true)
            ) {
                return ['duplicate' => true, 'processed' => true];
            }

            $providerReference = (string) (
                $references['provider_reference']
                ?? $payment->provider_reference
                ?? ''
            );

            $verification = $provider->verify($providerReference);
            $this->finalizer->apply($payment, $verification, 'webhook');

            $event->update([
                'processed' => true,
                'processed_at' => now(),
                'next_attempt_at' => null,
                'safe_error' => null,
            ]);

            return [
                'duplicate' => ! $event->wasRecentlyCreated,
                'processed' => true,
                'merchant_reference' => $references['merchant_reference'] ?? '',
                'provider_reference' => $providerReference,
                'event_type' => $references['event_type'] ?? null,
            ];
        } catch (\Throwable $exception) {
            Log::error('Payment webhook processing failed.', [
                'provider' => $providerName,
                'payment_reference' => $payment->reference,
                'event_id' => $eventId,
                'exception' => $exception,
            ]);

            // Do not mark a transient verification failure as permanently
            // processed. Scheduled reconciliation remains the fallback.
            $event->update([
                'processed' => false,
                'processed_at' => null,
                'next_attempt_at' => now()->addMinutes(5),
                'safe_error' => 'Webhook verification failed; scheduled reconciliation will retry.',
            ]);

            return [
                'duplicate' => ! $event->wasRecentlyCreated,
                'processed' => false,
                'error' => true,
                'merchant_reference' => $references['merchant_reference'] ?? '',
                'provider_reference' => $references['provider_reference'] ?? '',
                'event_type' => $references['event_type'] ?? null,
            ];
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
