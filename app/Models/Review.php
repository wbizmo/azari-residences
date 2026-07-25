<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Review extends Model {protected $guarded=[]; protected $perPage=10; protected function casts():array{return ['featured'=>'boolean','moderated_at'=>'datetime'];} public function booking():BelongsTo{return $this->belongsTo(Booking::class);} public function user():BelongsTo{return $this->belongsTo(User::class);} public function moderator():BelongsTo{return $this->belongsTo(User::class,'moderated_by');}}
