<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DiningRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','user_id','dining_partner_id','trip_itinerary_id','booking_id',
        'status','idempotency_key','payload_hash','party_size','requested_for',
        'private_preferences','supplier_share_consent','provider_reference',
        'provider_confirmed_at','cancelled_at','arrival_reminder_sent_at'];
    protected $hidden = ['idempotency_key','payload_hash','private_preferences'];
    protected function casts(): array
    {
        return ['private_preferences'=>'encrypted:array','requested_for'=>'datetime',
            'supplier_share_consent'=>'boolean','provider_confirmed_at'=>'datetime',
            'cancelled_at'=>'datetime','arrival_reminder_sent_at'=>'datetime'];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function partner(): BelongsTo { return $this->belongsTo(DiningPartner::class,'dining_partner_id'); }
    public function itinerary(): BelongsTo { return $this->belongsTo(TripItinerary::class,'trip_itinerary_id'); }
}
