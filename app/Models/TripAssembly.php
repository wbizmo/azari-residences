<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripAssembly extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','trip_itinerary_id','user_id','idempotency_key','payload_hash',
        'status','item_snapshot','currency_totals','revision','reviewed_at'];
    protected $hidden = ['idempotency_key','payload_hash'];
    protected function casts(): array
    {
        return ['item_snapshot'=>'array','currency_totals'=>'array','reviewed_at'=>'datetime'];
    }
    public function itinerary(): BelongsTo { return $this->belongsTo(TripItinerary::class,'trip_itinerary_id'); }
}
