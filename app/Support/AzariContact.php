<?php

namespace App\Support;

final class AzariContact
{
    public static function domain(): string
    {
        $host = request()?->getHost();
        if (! is_string($host) || $host === '') {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'resavar.com';
        }

        return preg_replace('/^www\./i', '', strtolower($host)) ?: 'resavar.com';
    }

    public static function email(): string { return 'hello@'.self::domain(); }
    public static function phone(): string { return '+250799 643 143'; }
    public static function phoneHref(): string { return 'tel:+250799643143'; }
}
