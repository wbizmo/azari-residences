<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Promotion extends Model {protected $guarded=[]; protected $perPage=10; protected function casts():array{return ['is_active'=>'boolean','is_featured'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime'];} public function scopeVisible($q){return $q->where('is_active',true)->where(fn($x)=>$x->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($x)=>$x->whereNull('ends_at')->orWhere('ends_at','>=',now()));}}
