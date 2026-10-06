<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHeartbeat extends Model
{
    protected $fillable = ['component','last_seen_at','metadata'];
    protected function casts(): array { return ['last_seen_at'=>'datetime','metadata'=>'array']; }
}
