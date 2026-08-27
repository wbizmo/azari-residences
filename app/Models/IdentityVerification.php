<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class IdentityVerification extends Model
{
    public const PROVIDER_DOJAH = 'dojah';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
            'last_event_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookingGuest(): BelongsTo
    {
        return $this->belongsTo(BookingGuest::class);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED && $this->verified_at !== null;
    }

    public static function userIsVerified(int $userId): bool
    {
        if (! Schema::hasTable('identity_verifications')) {
            return false;
        }

        return static::query()
            ->where('provider', self::PROVIDER_DOJAH)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_VERIFIED)
            ->whereNotNull('verified_at')
            ->exists();
    }

    public static function guestIsVerified(int $guestId): bool
    {
        if (! Schema::hasTable('identity_verifications')) {
            return false;
        }

        return static::query()
            ->where('provider', self::PROVIDER_DOJAH)
            ->where('booking_guest_id', $guestId)
            ->where('status', self::STATUS_VERIFIED)
            ->whereNotNull('verified_at')
            ->exists();
    }
}
