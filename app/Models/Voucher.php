<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = ['code','name','discount_type','discount_value','maximum_discount','minimum_booking_value','starts_at','expires_at','total_usage_limit','per_customer_limit','is_active','created_by'];
    protected function casts(): array { return ['is_active'=>'boolean','starts_at'=>'datetime','expires_at'=>'datetime','discount_value'=>'decimal:2','maximum_discount'=>'decimal:2','minimum_booking_value'=>'decimal:2']; }
    public function properties(): BelongsToMany { return $this->belongsToMany(Property::class, 'voucher_property'); }
    public function redemptions(): HasMany { return $this->hasMany(VoucherRedemption::class); }
    public function setCodeAttribute(string $value): void { $this->attributes['code'] = strtoupper(trim($value)); }
}
