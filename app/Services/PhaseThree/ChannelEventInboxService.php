<?php

namespace App\Services\PhaseThree;

use App\Models\ChannelConnection;
use App\Models\ChannelWebhookInbox;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Durable authentication and dedupe; intentionally not a certified OTA mutation processor. */
final class ChannelEventInboxService
{
    public function capture(ChannelConnection $connection, string $body, string $eventId, string $timestamp, string $signature): array
    {
        if (strlen($body) > 65536 || $body === '' || ! json_validate($body)) {
            abort(413, 'Invalid or oversized partner event.');
        }
        if (! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $eventId)
            || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            throw new AccessDeniedHttpException('Event authentication failed.');
        }
        // No external provider is allowed to send synthetic webhooks as iCal.
        // A gated local sandbox supports deterministic integration testing only.
        if ($connection->provider !== 'resavar_sandbox'
            || ! config('reserva.channels.sandbox_webhooks_enabled', false)
            || ! $connection->is_active || ! $connection->webhook_secret) {
            throw new AccessDeniedHttpException('Provider webhook is unavailable.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$eventId.'.'.$body, $connection->webhook_secret);
        if (! preg_match('/^[a-f0-9]{64}$/D', $signature) || ! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Event authentication failed.');
        }
        $digest = hash('sha256', $body);
        return DB::transaction(function () use ($connection, $eventId, $timestamp, $digest, $body): array {
            // Single authoritative locked connection prevents late writes after pause/revocation.
            $current = ChannelConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $current->is_active || $current->status === 'disconnected_pending_reconciliation'
                || $current->status === 'disconnected') {
                throw new AccessDeniedHttpException('Provider webhook is unavailable.');
            }
            $created = DB::table('channel_webhook_inbox')->insertOrIgnore([
                'channel_connection_id' => $current->id,
                'external_event_id' => $eventId,
                'payload_sha256' => $digest,
                'encrypted_payload' => Crypt::encryptString($body),
                'status' => 'received',
                'event_occurred_at' => now()->setTimestamp((int) $timestamp),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $existing = ChannelWebhookInbox::query()
                ->where('channel_connection_id', $current->id)->where('external_event_id', $eventId)->firstOrFail();
            if (! hash_equals($existing->payload_sha256, $digest)) {
                throw new ConflictHttpException('Event ID was reused with a different body.');
            }
            return ['id' => $existing->id, 'duplicate' => $created === 0, 'status' => $existing->status];
        }, 3);
    }
}
