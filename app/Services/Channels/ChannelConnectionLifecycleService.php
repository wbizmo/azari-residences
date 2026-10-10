<?php

namespace App\Services\Channels;

use App\Models\AuditLog;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Disconnect without releasing external reservations or erasing audit history.
 * A property with unreconciled future stays remains unavailable until a later,
 * explicit operator reconciliation flow is implemented and verified.
 */
final class ChannelConnectionLifecycleService
{
    public const PENDING = 'disconnected_pending_reconciliation';
    public const DISCONNECTED = 'disconnected';

    public function disconnect(ChannelConnection $connection, ?int $actorId = null): ChannelConnection
    {
        return DB::transaction(function () use ($connection, $actorId): ChannelConnection {
            $locked = ChannelConnection::query()->whereKey($connection->getKey())
                ->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [self::PENDING, self::DISCONNECTED], true)) {
                return $locked;
            }

            $pending = ChannelReservation::query()
                ->where('channel_connection_id', $locked->getKey())
                ->where('status', 'active')
                ->where('ends_on', '>', now()->toDateString())
                ->exists();

            // Revoke the public calendar token, disable synchronization and
            // erase possibly signed feed URLs; retain external stay records.
            $locked->forceFill([
                'is_active' => false,
                'fail_closed' => true,
                'status' => $pending ? self::PENDING : self::DISCONNECTED,
                'import_url' => null,
                'export_token' => Str::random(48),
                'next_retry_at' => null,
                'last_safe_error' => $pending
                    ? 'External reservations require operator reconciliation before inventory may reopen.'
                    : null,
            ])->save();

            AuditLog::record('channel.connection_disconnected', $locked,
                ['property_id' => $locked->property_id, 'provider' => $locked->provider],
                ['status' => $locked->status, 'actor_id' => $actorId]);

            return $locked;
        }, 3);
    }
}
