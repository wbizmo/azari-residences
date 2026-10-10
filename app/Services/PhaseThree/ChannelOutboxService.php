<?php
namespace App\Services\PhaseThree;
use App\Models\ChannelConnection;
use App\Models\ChannelOutboxEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Record an intent inside the caller's DB transaction, never send on rollback. */
final class ChannelOutboxService
{
    public function enqueue(ChannelConnection $connection, string $eventType, string $key, array $payload): ChannelOutboxEvent
    {
        if (! preg_match('/^[a-z_.-]{1,80}$/D', $eventType)
            || ! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key)
            || strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 65536) {
            throw ValidationException::withMessages(['event' => 'Invalid channel publication intent.']);
        }
        return DB::transaction(function () use ($connection, $eventType, $key, $payload): ChannelOutboxEvent {
            $locked = ChannelConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active || $locked->provider !== 'resavar_sandbox'
                || ! config('reserva.channels.sandbox_webhooks_enabled', false)
                || in_array($locked->status, ['disconnected','disconnected_pending_reconciliation','conflict'], true)) {
                throw ValidationException::withMessages(['provider' => 'Provider publication is not approved.']);
            }
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            DB::table('channel_outbox')->insertOrIgnore([
                'channel_connection_id' => $locked->id,
                'event_type' => $eventType,
                'idempotency_key' => $key,
                'encrypted_payload' => Crypt::encryptString($encoded),
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $existing = ChannelOutboxEvent::query()->where('channel_connection_id', $locked->id)
                ->where('idempotency_key', $key)->firstOrFail();
            if ($existing->event_type !== $eventType
                || ! hash_equals(hash('sha256', $encoded),
                    hash('sha256', Crypt::decryptString($existing->encrypted_payload)))) {
                throw ValidationException::withMessages(['event' => 'Idempotency key already belongs to another publication.']);
            }
            return $existing;
        }, 3);
    }
}
