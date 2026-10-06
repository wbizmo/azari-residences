<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Services\Channels\ChannelAdapterManager;
use Illuminate\Http\Response;

class ChannelCalendarController extends Controller
{
    public function __invoke(string $token, ChannelAdapterManager $adapters): Response
    {
        $connection = ChannelConnection::query()->with('property')->where('export_token', $token)->where('is_active', true)->firstOrFail();
        $body = $adapters->for($connection->provider)->export($connection->property, $connection->accommodation_type_id);
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
