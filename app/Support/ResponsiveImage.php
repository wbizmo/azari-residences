<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ResponsiveImage
{
    public static function isManagedPath(?string $path): bool
    {
        $value = trim((string) $path);

        return $value !== ''
            && ! Str::startsWith($value, ['http://', 'https://', '/', 'data:'])
            && ! str_contains($value, '..');
    }

    public static function originalUrl(string $path): string
    {
        if (! self::isManagedPath($path)) {
            return $path;
        }

        if (is_file(public_path($path))) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    public static function srcset(string $path, string $format): ?string
    {
        if (! self::isManagedPath($path) || is_file(public_path($path)) || ! in_array($format, ['webp', 'avif'], true)) {
            return null;
        }

        $disk = Storage::disk('public');
        $items = [];
        foreach ((array) config('reserva.media.responsive_widths', [480, 768, 1200]) as $width) {
            $variant = self::variantPath($path, (int) $width, $format);
            if ($disk->exists($variant)) {
                $items[] = $disk->url($variant).' '.((int) $width).'w';
            }
        }

        return $items === [] ? null : implode(', ', $items);
    }

    public static function variantPath(string $path, int $width, string $format): string
    {
        $directory = trim(dirname($path), '.\/');
        $stem = pathinfo($path, PATHINFO_FILENAME);
        $prefix = $directory === '' ? '' : $directory.'/';

        return $prefix.'.responsive/'.$stem.'-'.$width.'.'.$format;
    }

    public static function deleteDerivatives(string $path): void
    {
        if (! self::isManagedPath($path)) {
            return;
        }

        $disk = Storage::disk('public');
        foreach ((array) config('reserva.media.formats', ['webp', 'avif']) as $format) {
            foreach ((array) config('reserva.media.responsive_widths', [480, 768, 1200]) as $width) {
                $disk->delete(self::variantPath($path, (int) $width, (string) $format));
            }
        }
    }
}
