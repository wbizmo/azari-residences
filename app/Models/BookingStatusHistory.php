<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingStatusHistory extends Model {
    protected $perPage = 10;
    protected $fillable = ['booking_id','changed_by','from_status','to_status','note','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
}
