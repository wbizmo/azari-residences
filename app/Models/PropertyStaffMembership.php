<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyStaffMembership extends Model
{
    protected $guarded = [];
    protected $casts = ['capabilities' => 'array', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
}
