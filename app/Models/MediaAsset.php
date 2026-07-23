<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $fillable = [
        'disk', 'path', 'original_name', 'mime_type', 'size',
        'title', 'alt_text', 'caption', 'description', 'is_archived',
    ];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
