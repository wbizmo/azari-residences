<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeRevision extends Model
{
    protected $fillable = [
        'name', 'settings', 'status', 'created_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
