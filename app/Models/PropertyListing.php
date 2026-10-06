<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PropertyListing extends Model
{
    protected $guarded = [];
    protected $perPage = 10;

    protected function casts(): array
    {
        return [
            'property_data' => 'array',
            'amenity_ids' => 'array',
            'gallery' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
            'proposed_owner_share_percentage' => 'decimal:2',
            'approved_owner_share_percentage' => 'decimal:2',
            'completion_snapshot' => 'array',
            'publication_blockers' => 'array',
            'last_completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $listing): void {
            $listing->reference ??= 'LST-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
            $listing->proposed_owner_share_percentage ??= (float) config('azari.owners.default_owner_share_percentage', 88);
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function agreement(): BelongsTo { return $this->belongsTo(ListingAgreement::class, 'listing_agreement_id'); }
    public function approvedProperty(): BelongsTo { return $this->belongsTo(Property::class, 'approved_property_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'declined'], true);
    }
}
