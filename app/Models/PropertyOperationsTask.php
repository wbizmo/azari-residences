<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyOperationsTask extends Model
{
    protected $guarded = [];
    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'checklist' => 'array',
    ];
}
