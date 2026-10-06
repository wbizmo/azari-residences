<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class LocalDate
{
    public static function timezone(?string $timezone = null): string
    {
        $candidate = $timezone ?: config('localization.platform_timezone', config('app.timezone', 'UTC'));

        try {
            new \DateTimeZone((string) $candidate);
            return (string) $candidate;
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    public static function date(DateTimeInterface|string|null $value, ?string $timezone = null, string $format = 'j M Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return CarbonImmutable::parse($value)->setTimezone(self::timezone($timezone))->format($format);
    }

    public static function dateTime(DateTimeInterface|string|null $value, ?string $timezone = null, string $format = 'j M Y, g:i A T'): string
    {
        return self::date($value, $timezone, $format);
    }

    public static function propertyTimezone(object $property): string
    {
        return self::timezone(
            $property->timezone
            ?? $property->locationRecord?->timezone
            ?? null
        );
    }
}
