<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WithdrawalRequest extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'destination_snapshot' => 'array',
            'provider_response' => 'array',
            'requested_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'provider_sent_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'reconciliation_required_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->reference ??= 'WDR-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
            $request->requested_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isSafelyRetryable(): bool
    {
        return $this->status === 'failed'
            && blank($this->provider_reference)
            && blank($this->provider_sent_at)
            && blank($this->reconciliation_required_at);
    }
}
