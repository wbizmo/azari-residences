<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BackupRun extends Model {protected $guarded=[]; protected $perPage=10; protected function casts():array{return ['started_at'=>'datetime','finished_at'=>'datetime','verified_at'=>'datetime','metadata'=>'array'];}}
