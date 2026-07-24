<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAddOn extends Model {
    protected $perPage = 10;
    protected $fillable = ['name','description','pricing_type','price','is_active','sort_order'];
    protected function casts(): array { return ['price'=>'decimal:2','is_active'=>'boolean']; }
}
