<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'verified_stay' => 'boolean',
            'moderated_at' => 'datetime',
            'management_replied_at' => 'datetime',
            'hidden_at' => 'datetime',
            'restored_at' => 'datetime',
            'edited_at' => 'datetime',
            'owner_replied_at' => 'datetime',
        ];
    }

    public function appeal(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(ReviewAppeal::class); }
    public function helpfulVotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function moderator(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }
    public function managementReplyBy(): BelongsTo { return $this->belongsTo(User::class, 'management_reply_by'); }

    public function categoryScores(): array
    {
        return collect([
            'cleanliness' => $this->cleanliness,
            'comfort' => $this->comfort,
            'facilities' => $this->facilities,
            'location' => $this->location_score,
            'staff_service' => $this->staff_service,
            'value' => $this->value_score,
            'wifi' => $this->wifi_score,
        ])->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value)
            ->all();
    }
}
