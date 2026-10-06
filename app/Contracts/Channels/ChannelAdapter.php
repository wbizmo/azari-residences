<?php

namespace App\Contracts\Channels;

use App\Models\ChannelConnection;
use App\Models\Property;

interface ChannelAdapter
{
    /** @return array<int, array<string, mixed>> */
    public function import(ChannelConnection $connection): array;

    public function export(Property $property, ?int $accommodationTypeId = null): string;
}
