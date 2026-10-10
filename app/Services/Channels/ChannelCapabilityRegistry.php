<?php

namespace App\Services\Channels;

use InvalidArgumentException;

/**
 * Explicit, versioned supply-provider capabilities. A name here does not grant
 * commercial API access; external OTA integrations are disabled until certified.
 */
final class ChannelCapabilityRegistry
{
    private const MANIFESTS = [
        'ical' => [
            'contract_version' => 1,
            'enabled' => true,
            'requires_provider_approval' => false,
            'capabilities' => [
                'reservation_import' => true,
                'calendar_export' => true,
                'reservation_write' => false,
                'availability_rate_inventory_publish' => false,
                'cancellation_write' => false,
                'messaging' => false,
                'webhooks' => false,
            ],
        ],
        'booking_com' => [
            'contract_version' => 1, 'enabled' => false, 'requires_provider_approval' => true,
            'capabilities' => [],
        ],
        'expedia' => [
            'contract_version' => 1, 'enabled' => false, 'requires_provider_approval' => true,
            'capabilities' => [],
        ],
        'airbnb' => [
            'contract_version' => 1, 'enabled' => false, 'requires_provider_approval' => true,
            'capabilities' => [],
        ],
        'pms' => [
            'contract_version' => 1, 'enabled' => false, 'requires_provider_approval' => true,
            'capabilities' => [],
        ],
    ];

    public function manifest(string $provider): array
    {
        $normalized = strtolower(trim($provider));
        if (! array_key_exists($normalized, self::MANIFESTS)) {
            throw new InvalidArgumentException('Unsupported channel provider.');
        }

        return ['provider' => $normalized, ...self::MANIFESTS[$normalized]];
    }

    public function supports(string $provider, string $capability): bool
    {
        $manifest = $this->manifest($provider);

        return $manifest['enabled'] === true
            && ($manifest['capabilities'][$capability] ?? false) === true;
    }
}
