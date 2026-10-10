<?php

namespace App\Services\Channels;

use App\Contracts\Channels\ChannelAdapter;
use InvalidArgumentException;

class ChannelAdapterManager
{
    public function __construct(
        private readonly ICalChannelAdapter $ical,
        private readonly ChannelCapabilityRegistry $capabilities
    ) {}

    public function capabilities(string $provider): array
    {
        return $this->capabilities->manifest($provider);
    }
    public function for(string $provider): ChannelAdapter
    {
        if (! $this->capabilities->manifest($provider)['enabled']) {
            throw new InvalidArgumentException('Provider connector is not approved or installed.');
        }

        return match (strtolower(trim($provider))) {
            'ical' => $this->ical,
            default => throw new InvalidArgumentException('Unsupported channel provider.'),
        };
    }
}
