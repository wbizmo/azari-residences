<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChannelConnection extends Model
{
    protected $fillable = ['property_id','accommodation_type_id','provider','name','import_url','export_token','is_active','fail_closed','stale_after_minutes','status','last_attempted_at','last_successful_sync_at','next_retry_at','consecutive_failures','last_safe_error','settings'];
    protected $hidden = ['import_url', 'export_token'];
    protected function casts(): array { return ['is_active'=>'boolean','fail_closed'=>'boolean','last_attempted_at'=>'datetime','last_successful_sync_at'=>'datetime','next_retry_at'=>'datetime','settings'=>'array']; }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function accommodationType(): BelongsTo { return $this->belongsTo(AccommodationType::class); }
    public function reservations(): HasMany { return $this->hasMany(ChannelReservation::class); }
    public function runs(): HasMany { return $this->hasMany(ChannelSyncRun::class); }
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->export_token)) {
                $model->export_token = Str::random(48);
            }
        });
    }
}
