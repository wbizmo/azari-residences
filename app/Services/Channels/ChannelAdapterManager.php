<?php

namespace App\Services\Channels;

use App\Contracts\Channels\ChannelAdapter;
use InvalidArgumentException;

class ChannelAdapterManager
{
    public function __construct(private readonly ICalChannelAdapter $ical) {}
    public function for(string $provider): ChannelAdapter
    {
        return match (strtolower($provider)) {
            'ical' => $this->ical,
            default => throw new InvalidArgumentException('Unsupported channel provider.'),
        };
    }
}
