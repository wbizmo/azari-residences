<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class SupportTicket extends Model {
 protected $guarded=[]; protected $perPage=10;
 protected function casts():array{return ['escalated_at'=>'datetime','resolved_at'=>'datetime','closed_at'=>'datetime','first_responded_at'=>'datetime','response_due_at'=>'datetime','sla_due_at'=>'datetime','sla_alerted_at'=>'datetime'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function booking():BelongsTo{return $this->belongsTo(Booking::class);}
 public function assignee():BelongsTo{return $this->belongsTo(User::class,'assigned_to');}
 public function messages():HasMany{return $this->hasMany(SupportTicketMessage::class)->oldest();}
 public function publicMessages():HasMany{return $this->messages()->where('internal',false);}
 public static function nextReference(): string { return 'AZR-SUP-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(10)); }
}
