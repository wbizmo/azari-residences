<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TripAssemblyStep extends Model
{
    protected $guarded=[];
    protected function casts(): array { return ['last_checked_at'=>'datetime']; }
    public function assembly(): BelongsTo { return $this->belongsTo(TripAssembly::class,'trip_assembly_id'); }
}
