<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model
{
    protected $perPage = 10;

    protected $fillable = ['name','slug','description','is_system'];
    public function users(){ return $this->belongsToMany(User::class); }
    public function permissions(){ return $this->belongsToMany(Permission::class); }
}
