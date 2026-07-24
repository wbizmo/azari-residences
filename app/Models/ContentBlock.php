<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ContentBlock extends Model
{
    protected $perPage = 10;

    protected $fillable = [
        'page',
        'key',
        'label',
        'value',
        'type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function content(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('content_blocks')) {
            return $default;
        }

        return Cache::remember(
            "content-block:{$key}",
            now()->addMinutes(30),
            fn () => static::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->value('value') ?? $default
        );
    }

    protected static function booted(): void
    {
        static::saved(fn (self $block) => Cache::forget("content-block:{$block->key}"));
        static::deleted(fn (self $block) => Cache::forget("content-block:{$block->key}"));
    }
}
