<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VoucherRedemption extends Model {
    protected $fillable=['voucher_id','booking_id','user_id','guest_email','discount_amount','redeemed_at'];
    protected function casts():array{return ['discount_amount'=>'decimal:2','redeemed_at'=>'datetime'];}
    public function voucher(){return $this->belongsTo(Voucher::class);}
    public function booking(){return $this->belongsTo(Booking::class);}
}
