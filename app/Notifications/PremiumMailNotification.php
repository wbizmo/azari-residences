<?php

namespace App\Notifications;

use App\Models\CommunicationLog;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\Channels\TwilioWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PremiumMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $template,
        public string $subject,
        public array $lines = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) return ['mail'];

        $channels = [];
        if ((bool) ($notifiable->email_notifications ?? true) && filled($notifiable->email ?? null)) $channels[] = 'mail';
        if ((bool) ($notifiable->sms_notifications ?? false) && filled($notifiable->phone ?? null)) $channels[] = TwilioSmsChannel::class;
        if ((bool) ($notifiable->whatsapp_notifications ?? false) && filled($notifiable->phone ?? null)) $channels[] = TwilioWhatsAppChannel::class;
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof AnonymousNotifiable
            ? (string) $notifiable->routeNotificationFor('mail')
            : (string) ($notifiable->email ?? '');

        CommunicationLog::create([
            'channel' => 'email', 'template' => $this->template,
            'booking_id' => $this->context['booking_id'] ?? null,
            'user_id' => $notifiable->id ?? null, 'recipient' => $email,
            'masked_recipient' => $this->mask($email), 'provider' => config('mail.default'),
            'status' => 'queued', 'queued_at' => now(),
            'meta' => array_merge($this->context, ['notification_id' => $this->id]),
        ]);

        return (new MailMessage)->subject($this->subject)->view('emails.premium', [
            'title' => $this->subject, 'lines' => $this->lines,
            'actionLabel' => $this->actionLabel, 'actionUrl' => $this->actionUrl,
            'preheader' => $this->lines[0] ?? $this->subject,
        ]);
    }

    public function toSms(object $notifiable): string { return $this->plainMessage(); }
    public function toWhatsApp(object $notifiable): string { return $this->plainMessage(); }

    public function failed(?\Throwable $e): void
    {
        CommunicationLog::query()->where('meta->notification_id', $this->id)->update([
            'status' => 'failed', 'failed_at' => now(),
            'safe_error' => Str::limit((string) $e?->getMessage(), 240),
        ]);
    }

    private function plainMessage(): string
    {
        $body = trim($this->subject."\n\n".implode("\n", array_map('strip_tags', $this->lines)));
        if ($this->actionUrl) $body .= "\n\n".($this->actionLabel ?: 'Open').': '.$this->actionUrl;
        return Str::limit($body, 1400, '…');
    }

    private function mask(string $email): string
    {
        return preg_match('/^(.)(.*)(@.*)$/', $email, $m) ? $m[1].'***'.$m[3] : '***';
    }
}
