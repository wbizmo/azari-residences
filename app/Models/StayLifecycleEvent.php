<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class StayLifecycleEvent extends Model {protected $fillable=['booking_id','actor_id','event','note','viewer_timezone','operational_timezone','ip_address','user_agent']; public function booking(){return $this->belongsTo(Booking::class);} public function actor(){return $this->belongsTo(User::class,'actor_id');}}
