<?php

namespace App\Notifications;

use App\Models\CommunicationLog;
use App\Models\SiteSetting;
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
        public array $details = [],
        public ?string $notice = null,
        public string $tone = 'default',
        public ?string $secondaryActionLabel = null,
        public ?string $secondaryActionUrl = null,
        public bool $forceDelivery = false,
        public bool $mailOnly = false,
        public ?string $eyebrow = null,
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        $channels = [];

        if (
            ($this->forceDelivery || (bool) ($notifiable->email_notifications ?? true))
            && filled($notifiable->email ?? null)
        ) {
            $channels[] = 'mail';
        }

        if ($this->mailOnly) {
            return $channels;
        }

        if ((bool) ($notifiable->sms_notifications ?? false) && filled($notifiable->phone ?? null)) {
            $channels[] = TwilioSmsChannel::class;
        }

        if ((bool) ($notifiable->whatsapp_notifications ?? false) && filled($notifiable->phone ?? null)) {
            $channels[] = TwilioWhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof AnonymousNotifiable
            ? (string) $notifiable->routeNotificationFor('mail')
            : (string) ($notifiable->email ?? '');

        $attributes = [
            'channel' => 'email',
            'template' => $this->template,
            'booking_id' => $this->context['booking_id'] ?? null,
            'user_id' => $this->context['user_id'] ?? ($notifiable->id ?? null),
            'recipient' => $email,
            'masked_recipient' => $this->mask($email),
            'provider' => config('mail.default'),
            'status' => 'queued',
            'queued_at' => now(),
            'safe_error' => null,
            'meta' => array_merge($this->context, ['notification_id' => $this->id]),
        ];

        $logId = (int) ($this->context['communication_log_id'] ?? 0);
        if ($logId > 0) {
            CommunicationLog::query()->whereKey($logId)->update($attributes);
        } else {
            CommunicationLog::query()->create($attributes);
        }

        $supportEmail = SiteSetting::valueFor(
            'customer_dashboard_contact_email',
            SiteSetting::valueFor('public_contact_email', config('mail.from.address'))
        );

        return (new MailMessage)->subject($this->subject)->view('emails.premium', [
            'title' => $this->subject,
            'lines' => $this->lines,
            'actionLabel' => $this->actionLabel,
            'actionUrl' => $this->actionUrl,
            'secondaryActionLabel' => $this->secondaryActionLabel,
            'secondaryActionUrl' => $this->secondaryActionUrl,
            'preheader' => $this->lines[0] ?? $this->subject,
            'details' => $this->details,
            'notice' => $this->notice,
            'tone' => $this->tone,
            'eyebrow' => $this->eyebrow ?: 'The Azari Residences',
            'logoUrl' => asset('images/logo-light.png'),
            'supportEmail' => $supportEmail,
            'footerText' => SiteSetting::valueFor(
                'email_transactional_footer',
                'This is a transactional message from The Azari Residences. Keep booking, payment and account links private.'
            ),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        return $this->plainMessage();
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->plainMessage();
    }

    public function failed(?\Throwable $exception): void
    {
        $query = CommunicationLog::query();
        $logId = (int) ($this->context['communication_log_id'] ?? 0);

        if ($logId > 0) {
            $query->whereKey($logId);
        } else {
            $query->where('meta->notification_id', $this->id);
        }

        $query->update([
            'status' => 'failed',
            'failed_at' => now(),
            'safe_error' => Str::limit((string) $exception?->getMessage(), 240),
        ]);
    }

    private function plainMessage(): string
    {
        $body = trim($this->subject."\n\n".implode("\n", array_map('strip_tags', $this->lines)));

        foreach ($this->details as $label => $value) {
            if (filled($value)) {
                $body .= "\n{$label}: {$value}";
            }
        }

        if ($this->actionUrl) {
            $body .= "\n\n".($this->actionLabel ?: 'Open').': '.$this->actionUrl;
        }

        return Str::limit($body, 1400, '…');
    }

    private function mask(string $email): string
    {
        return preg_match('/^(.)(.*)(@.*)$/', $email, $matches)
            ? $matches[1].'***'.$matches[3]
            : '***';
    }
}
