<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingHold extends Model {
    protected $perPage = 10;
    protected $fillable = ['property_id','user_id','token','check_in','check_out','adults','children','rooms','expires_at'];
    protected function casts(): array { return ['check_in'=>'date','check_out'=>'date','expires_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $h) => $h->token ??= (string) Str::uuid()); }
    public function scopeActive(Builder $q): Builder { return $q->where('expires_at','>',now()); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
