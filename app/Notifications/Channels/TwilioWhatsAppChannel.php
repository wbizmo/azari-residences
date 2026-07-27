<?php

namespace App\Notifications\Channels;

use App\Contracts\Communication\SmsProvider;
use App\Models\CommunicationLog;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class TwilioWhatsAppChannel
{
    public function __construct(private readonly SmsProvider $provider) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp') || blank($notifiable->phone ?? null)) return;
        $message = (string) $notification->toWhatsApp($notifiable);
        $log = CommunicationLog::create([
            'channel' => 'whatsapp', 'template' => $notification->template ?? class_basename($notification),
            'booking_id' => $notification->context['booking_id'] ?? null, 'user_id' => $notifiable->id ?? null,
            'recipient' => $notifiable->phone, 'masked_recipient' => $this->mask($notifiable->phone),
            'provider' => 'twilio_whatsapp', 'status' => 'queued', 'queued_at' => now(),
        ]);
        try {
            $options = ['content_sid' => config('services.twilio.whatsapp_content_sids.'.($notification->template ?? ''))];
            $result = $this->provider->sendWhatsApp($notifiable->phone, $message, array_filter($options));
            $log->update(['status' => 'sent', 'sent_at' => now(), 'provider_reference' => $result['sid'] ?? null]);
        } catch (\Throwable $e) {
            Log::error('Azari WhatsApp delivery failed', ['log_id' => $log->id, 'exception' => $e]);
            $log->update(['status' => 'failed', 'failed_at' => now(), 'safe_error' => 'The WhatsApp message could not be delivered.', 'retry_count' => $log->retry_count + 1]);
        }
    }

    private function mask(string $value): string { return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4); }
}
