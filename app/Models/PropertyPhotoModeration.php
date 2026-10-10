<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPhotoModeration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function pathHash(string $path): string
    {
        return hash('sha256', $path);
    }
}
