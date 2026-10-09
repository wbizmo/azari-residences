<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const SUCCESSFUL = 'successful';
    public const PENDING = 'pending';
    public const FAILED = 'failed';

    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'due_on' => 'date',
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
            'abandoned_at' => 'datetime',
            'provider_response_summary' => 'array',
            'safe_metadata' => 'array',
        ];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function events(): HasMany { return $this->hasMany(PaymentEvent::class)->latest('received_at'); }
    public function verificationAttempts(): HasMany { return $this->hasMany(PaymentVerificationAttempt::class)->latest('attempted_at'); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class)->latest(); }

    public function isSuccessful(): bool { return $this->status === self::SUCCESSFUL; }
    public function canRetry(): bool { return in_array($this->status, ['failed', 'abandoned', 'pending', 'initiated'], true); }

    public function refundableBalance(): float
    {
        if (! $this->isSuccessful()
            && ! ($this->status === 'successful_excess' && $this->verified_at !== null)) {
            return 0.0;
        }

        $refunded = (float) $this->refunds()
            ->where('status', 'successful')
            ->sum('amount');

        return max(0, round((float) $this->amount - $refunded, 2));
    }
}
