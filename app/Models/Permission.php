<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Permission extends Model
{
    protected $perPage = 10;

    protected $fillable = ['name','slug','group'];
}
