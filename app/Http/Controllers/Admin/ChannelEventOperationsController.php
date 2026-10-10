<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChannelWebhookInbox;
use App\Models\ChannelOutboxEvent;
use Illuminate\Http\JsonResponse;

/** Read-only operational inbox without secrets, customer data or raw payloads. */
final class ChannelEventOperationsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $inbox = ChannelWebhookInbox::query()->select([
            'id','channel_connection_id','external_event_id','status','attempts',
            'safe_error','created_at','processed_at',
        ])->latest('id')->limit(40)->get();
        $outbox = ChannelOutboxEvent::query()->select([
            'id','channel_connection_id','event_type','status','attempts',
            'safe_error','created_at','published_at',
        ])->latest('id')->limit(40)->get();
        return response()->json([
            'inbox_recent'=>$inbox,'outbox_recent'=>$outbox,
            'pending_inbox'=>ChannelWebhookInbox::query()->whereIn('status',['received','retry'])->count(),
            'manual_review'=>ChannelWebhookInbox::query()->whereIn('status',['manual_review','dead_letter'])->count(),
            'pending_outbox'=>ChannelOutboxEvent::query()->where('status','pending')->count(),
            'outbox_dead_letter'=>ChannelOutboxEvent::query()->where('status','dead_letter')->count(),
            'external_provider_publication_active'=>false,
        ]);
    }
}
