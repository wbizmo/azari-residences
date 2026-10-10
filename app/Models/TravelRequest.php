<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'trip_itinerary_id', 'booking_id', 'travel_supplier_id',
        'travel_offer_id', 'travel_experience_slot_id', 'kind', 'status',
        'idempotency_key', 'payload_hash', 'party_size', 'quoted_total_minor',
        'currency', 'quote_snapshot', 'preferences', 'data_share_consent',
        'supplier_reference', 'supplier_acknowledged_at', 'expires_at', 'cancelled_at',
    ];

    protected $hidden = ['idempotency_key', 'payload_hash'];

    protected function casts(): array
    {
        return [
            'quoted_total_minor' => 'integer',
            'quote_snapshot' => 'array', 'preferences' => 'encrypted:array',
            'data_share_consent' => 'boolean', 'expires_at' => 'datetime',
            'cancelled_at' => 'datetime', 'supplier_acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fulfillment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TravelFulfillment::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(TravelOffer::class, 'travel_offer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(TravelSupplier::class, 'travel_supplier_id');
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(TripItinerary::class, 'trip_itinerary_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TravelExperienceSlot::class, 'travel_experience_slot_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TravelRequestEvent::class)->orderBy('id');
    }
}
