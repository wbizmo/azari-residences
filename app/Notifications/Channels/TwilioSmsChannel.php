<?php

namespace App\Notifications\Channels;

use App\Contracts\Communication\SmsProvider;
use App\Models\CommunicationLog;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class TwilioSmsChannel
{
    public function __construct(private readonly SmsProvider $provider) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms') || blank($notifiable->phone ?? null)) {
            return;
        }

        $message = (string) $notification->toSms($notifiable);
        $dedupeKey = (string) ($notification->context['dedupe_key'] ?? ($notification->id ?: class_basename($notification)));
        $idempotencyKey = hash('sha256', 'sms|'.$notifiable->phone.'|'.$dedupeKey);
        $explicitLogId = (int) ($notification->context['communication_log_id'] ?? 0);

        $log = $explicitLogId > 0
            ? CommunicationLog::query()->whereKey($explicitLogId)->where('channel', 'sms')->first()
            : null;

        if (! $log) {
            $log = CommunicationLog::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'channel' => 'sms',
                    'template' => $notification->template ?? class_basename($notification),
                    'booking_id' => $notification->context['booking_id'] ?? null,
                    'user_id' => $notifiable->id ?? null,
                    'recipient' => $notifiable->phone,
                    'masked_recipient' => $this->mask($notifiable->phone),
                    'provider' => 'twilio',
                    'status' => 'queued',
                    'queued_at' => now(),
                    'classification' => $notification->context['classification'] ?? 'transactional',
                    'locale' => $notification->context['locale'] ?? app()->getLocale(),
                    'timezone' => $notification->context['timezone'] ?? ($notifiable->timezone ?? config('localization.platform_timezone', 'UTC')),
                    'payload_hash' => hash('sha256', $message),
                    'meta' => [
                        'dedupe_key' => $dedupeKey,
                        'snapshot' => $notification->context['snapshot'] ?? null,
                    ],
                ]
            );

            if (! $log->wasRecentlyCreated && in_array($log->status, ['queued', 'sent', 'delivered'], true)) {
                return;
            }
        }

        $log->update([
            'status' => 'queued',
            'queued_at' => $log->queued_at ?: now(),
            'failed_at' => null,
            'safe_error' => null,
            'next_attempt_at' => null,
        ]);

        try {
            $result = $this->provider->send($notifiable->phone, $message);
            $providerStatus = strtolower((string) ($result['status'] ?? 'queued'));

            $log->update([
                'status' => in_array($providerStatus, ['sent', 'delivered', 'read'], true) ? 'sent' : 'queued',
                'provider_reference' => $result['sid'] ?? null,
                'provider_status' => $providerStatus,
                'status_updated_at' => now(),
                'sent_at' => in_array($providerStatus, ['sent', 'delivered', 'read'], true) ? now() : null,
                'delivered_at' => in_array($providerStatus, ['delivered', 'read'], true) ? now() : null,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Resarva SMS delivery failed', [
                'log_id' => $log->id,
                'exception' => $exception,
            ]);

            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'next_attempt_at' => now()->addMinutes(5),
                'safe_error' => 'The text message could not be delivered.',
                'retry_count' => $log->retry_count + 1,
            ]);
        }
    }

    private function mask(string $value): string
    {
        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
