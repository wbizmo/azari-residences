<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSyncRun extends Model
{
    protected $fillable = ['channel_connection_id','status','imported','updated','cancelled','safe_error','started_at','finished_at','metadata'];
    protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime','metadata'=>'array']; }
    public function connection(): BelongsTo { return $this->belongsTo(ChannelConnection::class, 'channel_connection_id'); }
}
