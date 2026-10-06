<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationLog extends Model
{
    protected $perPage = 10;

    protected $fillable = [
        'channel', 'template', 'booking_id', 'user_id', 'service_request_id',
        'recipient', 'masked_recipient', 'provider', 'provider_reference', 'status',
        'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'safe_error',
        'retry_count', 'meta', 'idempotency_key', 'classification', 'locale',
        'timezone', 'payload_hash', 'next_attempt_at', 'provider_status',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'status_updated_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
