<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class ServiceRequestEvent extends Model {protected $fillable=['service_request_id','actor_id','from_status','to_status','note','guest_visible']; protected function casts():array{return ['guest_visible'=>'boolean'];} public function request(){return $this->belongsTo(ServiceRequest::class,'service_request_id');} public function actor(){return $this->belongsTo(User::class,'actor_id');}}
