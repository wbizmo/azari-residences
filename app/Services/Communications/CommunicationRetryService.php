<?php

namespace App\Services\Communications;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class CommunicationRetryService
{
    public function retry(CommunicationLog $log): CommunicationLog
    {
        if (! in_array($log->channel, ['email', 'sms', 'whatsapp'], true)) {
            throw ValidationException::withMessages([
                'communication' => 'This communication channel cannot be retried manually.',
            ]);
        }

        if ($log->status !== 'failed') {
            throw ValidationException::withMessages([
                'communication' => 'Only failed communications can be retried.',
            ]);
        }

        $snapshot = (array) data_get($log->meta, 'snapshot', []);

        if (blank($snapshot['template'] ?? null) || blank($snapshot['subject'] ?? null)) {
            throw ValidationException::withMessages([
                'communication' => 'This historical communication does not contain a safe retry snapshot.',
            ]);
        }

        $user = $log->user_id ? User::query()->find($log->user_id) : null;
        $dedupeKey = (string) data_get($log->meta, 'dedupe_key', 'retry-'.$log->getKey());

        $notification = new PremiumMailNotification(
            template: (string) $snapshot['template'],
            subject: (string) $snapshot['subject'],
            lines: array_values((array) ($snapshot['lines'] ?? [])),
            actionLabel: $snapshot['action_label'] ?? null,
            actionUrl: $snapshot['action_url'] ?? null,
            context: [
                'booking_id' => $log->booking_id,
                'user_id' => $log->user_id,
                'dedupe_key' => $dedupeKey,
                'communication_log_id' => $log->getKey(),
                'classification' => $log->classification ?: 'transactional',
                'locale' => $log->locale,
                'timezone' => $log->timezone,
                'snapshot' => $snapshot,
            ],
            details: (array) ($snapshot['details'] ?? []),
            notice: $snapshot['notice'] ?? null,
            tone: (string) ($snapshot['tone'] ?? 'default'),
            secondaryActionLabel: $snapshot['secondary_action_label'] ?? null,
            secondaryActionUrl: $snapshot['secondary_action_url'] ?? null,
            forceDelivery: (bool) ($snapshot['critical'] ?? false),
            mailOnly: false,
            eyebrow: $snapshot['eyebrow'] ?? null,
            onlyChannel: $log->channel,
        );

        $log->update([
            'status' => 'queued',
            'queued_at' => now(),
            'failed_at' => null,
            'safe_error' => null,
            'next_attempt_at' => null,
            'retry_count' => ((int) $log->retry_count) + 1,
        ]);

        try {
            if ($user) {
                $user->notify($notification);
            } elseif ($log->channel === 'email') {
                Notification::route('mail', $log->recipient)->notify($notification);
            } else {
                throw ValidationException::withMessages([
                    'communication' => 'The user account required for this text-channel retry is no longer available.',
                ]);
            }
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'next_attempt_at' => now()->addMinutes(5),
                'safe_error' => 'The communication could not be queued for retry.',
            ]);

            throw $exception;
        }

        return $log->refresh();
    }
}
