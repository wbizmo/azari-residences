<?php

namespace App\Notifications;

use App\Models\CommunicationLog;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

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
        public ?string $onlyChannel = null,
    ) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return $this->onlyChannel && $this->onlyChannel !== 'email' ? [] : ['mail'];
        }

        $preference = method_exists($notifiable, 'communicationPreference')
            && method_exists($notifiable, 'getKey')
            && $notifiable->getKey()
            && Schema::hasTable('communication_preferences')
                ? $notifiable->communicationPreference()->first()
                : null;

        $emailAllowed = $this->forceDelivery
            || (bool) ($preference?->email_transactional ?? $notifiable->email_notifications ?? true);
        $inAppAllowed = (bool) ($preference?->in_app_transactional ?? true);

        $available = [];

        if ($emailAllowed && filled($notifiable->email ?? null)) {
            $available['email'] = 'mail';
        }

        if (! $this->mailOnly) {

            if ($inAppAllowed) {
                $available['database'] = 'database';
            }
        }

        if ($this->onlyChannel) {
            return isset($available[$this->onlyChannel])
                ? [$available[$this->onlyChannel]]
                : [];
        }

        return array_values($available);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof AnonymousNotifiable
            ? (string) $notifiable->routeNotificationFor('mail')
            : (string) ($notifiable->email ?? '');

        $dedupeKey = (string) ($this->context['dedupe_key'] ?? ($this->id ?: $this->template));
        $idempotencyKey = hash('sha256', 'email|'.strtolower($email).'|'.$dedupeKey);
        $payloadHash = hash('sha256', json_encode([
            $this->subject,
            $this->lines,
            $this->details,
            $this->actionUrl,
        ]));

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
            'classification' => $this->context['classification'] ?? 'transactional',
            'locale' => $this->context['locale'] ?? app()->getLocale(),
            'timezone' => $this->context['timezone'] ?? ($notifiable->timezone ?? config('localization.platform_timezone', 'UTC')),
            'payload_hash' => $payloadHash,
            'meta' => array_merge($this->context, ['notification_id' => $this->id]),
        ];

        $logId = (int) ($this->context['communication_log_id'] ?? 0);

        if ($logId > 0) {
            CommunicationLog::query()
                ->whereKey($logId)
                ->where('channel', 'email')
                ->update($attributes + ['idempotency_key' => $idempotencyKey]);
        } else {
            CommunicationLog::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                $attributes
            );
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
            'eyebrow' => $this->eyebrow ?: 'Resavar',
            'logoUrl' => asset('images/logo-light.png'),
            'supportEmail' => $supportEmail,
            'footerText' => SiteSetting::valueFor(
                'email_transactional_footer',
                'This is a transactional message from Resavar. Keep booking, payment and account links private.'
            ),
        ]);
    }

    public function toDatabase(object $notifiable): array
    {
        $dedupeKey = (string) ($this->context['dedupe_key'] ?? ($this->id ?: $this->template));
        $idempotencyKey = hash('sha256', 'in_app|'.($notifiable->id ?? 'anonymous').'|'.$dedupeKey);

        CommunicationLog::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'channel' => 'in_app',
                'template' => $this->template,
                'booking_id' => $this->context['booking_id'] ?? null,
                'user_id' => $notifiable->id ?? null,
                'recipient' => 'user:'.($notifiable->id ?? 'unknown'),
                'masked_recipient' => 'in-app',
                'provider' => 'database',
                'status' => 'delivered',
                'queued_at' => now(),
                'sent_at' => now(),
                'delivered_at' => now(),
                'classification' => $this->context['classification'] ?? 'transactional',
                'locale' => $this->context['locale'] ?? app()->getLocale(),
                'timezone' => $this->context['timezone'] ?? ($notifiable->timezone ?? config('localization.platform_timezone', 'UTC')),
                'payload_hash' => hash('sha256', json_encode([$this->subject, $this->lines, $this->actionUrl])),
                'meta' => array_merge($this->context, ['notification_id' => $this->id]),
            ]
        );

        return [
            'template' => $this->template,
            'subject' => $this->subject,
            'lines' => array_values(array_map(
                fn ($line) => Str::limit(strip_tags((string) $line), 300),
                array_slice($this->lines, 0, 4)
            )),
            'action_label' => $this->actionLabel,
            'action_url' => $this->actionUrl,
            'booking_id' => $this->context['booking_id'] ?? null,
            'classification' => $this->context['classification'] ?? 'transactional',
        ];
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
