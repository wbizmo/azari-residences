<?php

namespace App\Services\Communications;

use App\Contracts\Communication\SmsProvider;
use App\Models\{Booking, CommunicationLog, ServiceRequest, User};
use Illuminate\Support\Facades\{Log, Mail};

class CommunicationService
{
    public function __construct(private readonly SmsProvider $messaging) {}

    public function email(string $to, string $template, string $subject, string $html, ?Booking $booking = null, ?User $user = null, ?ServiceRequest $request = null): bool
    {
        if ($user && ! $user->email_notifications) return $this->skipped('email', $to, $template, $booking, $user, $request, 'Email notifications are disabled.');
        $log = $this->log('email', $to, $template, $booking, $user, $request, config('mail.default'));
        try {
            Mail::html($html, fn ($mail) => $mail->to($to)->subject($subject));
            $log->update(['status' => 'sent', 'sent_at' => now()]); return true;
        } catch (\Throwable $e) {
            Log::error('Azari email delivery failed', ['log_id' => $log->id, 'template' => $template, 'exception' => $e]);
            $log->update(['status' => 'failed', 'failed_at' => now(), 'safe_error' => 'The message could not be delivered.', 'retry_count' => $log->retry_count + 1]); return false;
        }
    }

    public function sms(string $to, string $template, string $message, ?Booking $booking = null, ?User $user = null): bool
    {
        if ($user && ! $user->sms_notifications) return $this->skipped('sms', $to, $template, $booking, $user, null, 'SMS notifications are disabled.');
        return $this->textChannel('sms', $to, $template, $message, $booking, $user);
    }

    public function whatsapp(string $to, string $template, string $message, ?Booking $booking = null, ?User $user = null): bool
    {
        if ($user && ! $user->whatsapp_notifications) return $this->skipped('whatsapp', $to, $template, $booking, $user, null, 'WhatsApp notifications are disabled.');
        return $this->textChannel('whatsapp', $to, $template, $message, $booking, $user);
    }

    private function textChannel(string $channel, string $to, string $template, string $message, ?Booking $booking, ?User $user): bool
    {
        $log = $this->log($channel, $to, $template, $booking, $user, null, $channel === 'whatsapp' ? 'twilio_whatsapp' : 'twilio');
        try {
            $result = $channel === 'whatsapp' ? $this->messaging->sendWhatsApp($to, $message) : $this->messaging->send($to, $message);
            $log->update(['status' => 'sent', 'sent_at' => now(), 'provider_reference' => $result['sid'] ?? null]); return true;
        } catch (\Throwable $e) {
            Log::error("Azari {$channel} delivery failed", ['log_id' => $log->id, 'exception' => $e]);
            $log->update(['status' => 'failed', 'failed_at' => now(), 'safe_error' => 'The message could not be delivered.', 'retry_count' => $log->retry_count + 1]); return false;
        }
    }

    private function log(string $channel, string $to, string $template, ?Booking $booking, ?User $user, ?ServiceRequest $request, ?string $provider): CommunicationLog
    {
        return CommunicationLog::create(['channel' => $channel, 'template' => $template, 'booking_id' => $booking?->id, 'user_id' => $user?->id, 'service_request_id' => $request?->id, 'recipient' => $to, 'masked_recipient' => str_contains($to, '@') ? $this->maskEmail($to) : $this->maskPhone($to), 'provider' => $provider, 'status' => 'queued', 'queued_at' => now()]);
    }

    private function skipped(string $channel, string $to, string $template, ?Booking $booking, ?User $user, ?ServiceRequest $request, string $reason): bool
    {
        $this->log($channel, $to, $template, $booking, $user, $request, $channel === 'email' ? config('mail.default') : 'twilio')->update(['status' => 'skipped', 'safe_error' => $reason]); return false;
    }

    private function maskEmail(string $value): string { [$a, $d] = array_pad(explode('@', $value, 2), 2, ''); return mb_substr($a, 0, 2).'***@'.$d; }
    private function maskPhone(string $value): string { return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4); }
}
