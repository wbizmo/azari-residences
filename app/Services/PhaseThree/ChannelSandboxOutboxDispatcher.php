<?php

namespace App\Services\PhaseThree;

use App\Models\ChannelConnection;
use App\Models\ChannelOutboxEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Explicit sandbox simulation, NOT provider publication. Never emit external
 * traffic until a certified adapter, acknowledgement and retry contract exists.
 */
final class ChannelSandboxOutboxDispatcher
{
    public function simulate(int $eventId): string
    {
        return DB::transaction(function () use ($eventId): string {
            $event = ChannelOutboxEvent::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($event->status !== 'pending') return $event->status;
            $connection = ChannelConnection::query()->whereKey($event->channel_connection_id)
                ->lockForUpdate()->firstOrFail();
            if ($connection->provider !== 'resavar_sandbox'
                || ! config('reserva.channels.sandbox_webhooks_enabled', false)
                || ! $connection->is_active || in_array($connection->status,
                    ['disconnected', 'disconnected_pending_reconciliation','conflict'], true)) {
                $event->update(['status'=>'dead_letter',
                    'safe_error'=>'Provider is not approved for outbound publication.',
                    'attempts'=>$event->attempts+1]);
                return 'dead_letter';
            }
            // Check payload decryptability, do not emit, log or expose the data.
            json_decode(Crypt::decryptString($event->encrypted_payload), true, 32, JSON_THROW_ON_ERROR);
            $event->update(['status'=>'simulated','published_at'=>null,
                'safe_error'=>'Sandbox simulation only. No external publication occurred.',
                'attempts'=>$event->attempts+1]);
            return 'simulated';
        }, 3);
    }

    public function drain(int $limit = 25): array
    {
        $ids = ChannelOutboxEvent::query()->where('status','pending')
            ->where(fn($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))
            ->orderBy('id')->limit(max(1,min($limit,100)))->pluck('id');
        $result = [];
        foreach ($ids as $id) $result[$id] = $this->simulate((int)$id);
        return $result;
    }
}
