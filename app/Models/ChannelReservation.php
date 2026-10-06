<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelReservation extends Model
{
    protected $fillable = ['channel_connection_id','property_id','accommodation_type_id','external_id','status','starts_on','ends_on','quantity','summary','source_hash','external_updated_at','last_seen_at','metadata'];
    protected function casts(): array { return ['starts_on'=>'date','ends_on'=>'date','external_updated_at'=>'datetime','last_seen_at'=>'datetime','metadata'=>'array']; }
    public function connection(): BelongsTo { return $this->belongsTo(ChannelConnection::class, 'channel_connection_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
}
