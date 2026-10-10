<?php
namespace App\Services\PhaseThree;

use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Services\Channels\ChannelAvailabilityService;

/** Read-only health and mapping readiness; never self-certifies an OTA account. */
final class ChannelMappingAuditService
{
    public function __construct(private readonly ChannelAvailabilityService $availability) {}

    public function inspect(ChannelConnection $connection): array
    {
        $connection->loadMissing(['property', 'accommodationType']);
        $issues = [];
        if (! $connection->is_active) $issues[] = 'Connection is paused or disconnected.';
        if (! $connection->property) $issues[] = 'Property mapping is missing.';
        if (! $connection->accommodation_type_id || ! $connection->accommodationType
            || (int) $connection->accommodationType->property_id !== (int) $connection->property_id) {
            $issues[] = 'An accommodation type belonging to this property is required.';
        }
        if ($connection->accommodationType && $connection->property
            && strtoupper((string) $connection->accommodationType->currency) !== strtoupper((string) $connection->property->currency)) {
            $issues[] = 'Room and property currencies differ; review before linking provider rates.';
        }
        if ($this->availability->isStale($connection)) $issues[] = 'Calendar synchronization has not been verified recently.';
        if ($connection->provider !== 'ical') $issues[] = 'Official provider integration and certification are not available.';
        $futureCount = ChannelReservation::query()->where('channel_connection_id', $connection->id)
            ->where('status', 'active')->where('ends_on', '>', now()->toDateString())->count();

        return [
            'connection_id' => $connection->id, 'provider' => $connection->provider,
            'ready_for_ical_reconciliation' => $issues === [],
            'certified_for_ari_publication' => false,
            'last_successful_sync_at' => $connection->last_successful_sync_at?->toIso8601String(),
            'future_external_reservations' => $futureCount,
            'issues' => $issues,
        ];
    }
}
