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
        if (! method_exists($notification, 'toSms') || blank($notifiable->phone ?? null)) return;
        $message = (string) $notification->toSms($notifiable);
        $log = CommunicationLog::create([
            'channel' => 'sms', 'template' => $notification->template ?? class_basename($notification),
            'booking_id' => $notification->context['booking_id'] ?? null, 'user_id' => $notifiable->id ?? null,
            'recipient' => $notifiable->phone, 'masked_recipient' => $this->mask($notifiable->phone),
            'provider' => 'twilio', 'status' => 'queued', 'queued_at' => now(),
        ]);
        try {
            $result = $this->provider->send($notifiable->phone, $message);
            $log->update(['status' => 'sent', 'sent_at' => now(), 'provider_reference' => $result['sid'] ?? null]);
        } catch (\Throwable $e) {
            Log::error('Azari SMS delivery failed', ['log_id' => $log->id, 'exception' => $e]);
            $log->update(['status' => 'failed', 'failed_at' => now(), 'safe_error' => 'The text message could not be delivered.', 'retry_count' => $log->retry_count + 1]);
        }
    }

    private function mask(string $value): string { return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4); }
}
