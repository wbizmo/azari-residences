<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    protected $perPage = 10;

    protected $fillable = ['key', 'value', 'type', 'group'];

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('site_settings')) {
            return $default;
        }

        return Cache::remember(
            "site-setting:{$key}",
            now()->addMinutes(30),
            fn () => static::query()->where('key', $key)->value('value') ?? $default
        );
    }

    public static function put(string $key, mixed $value, string $type = 'text', string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );

        Cache::forget("site-setting:{$key}");
    }
}
