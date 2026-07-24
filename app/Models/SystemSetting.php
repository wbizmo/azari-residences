<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $perPage = 10;

    protected $fillable = ['key', 'value'];
}
