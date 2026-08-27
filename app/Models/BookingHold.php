<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingHold extends Model
{
    protected $perPage = 10;

    protected $fillable = [
        'property_id',
        'user_id',
        'token',
        'check_in',
        'check_out',
        'adults',
        'children',
        'rooms',
        'guest_draft',
        'email_code_hash',
        'email_code_expires_at',
        'email_code_sent_at',
        'email_code_attempts',
        'email_code_locked_until',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'guest_draft' => 'array',
            'email_code_expires_at' => 'datetime',
            'email_code_sent_at' => 'datetime',
            'email_code_locked_until' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $hold) => $hold->token ??= (string) Str::uuid());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
