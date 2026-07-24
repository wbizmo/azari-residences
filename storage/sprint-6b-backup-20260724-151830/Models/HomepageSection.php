<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    protected $perPage = 10;

    protected $fillable = [
        'key', 'name', 'type', 'content', 'background_media',
        'status', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
