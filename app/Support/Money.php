<?php

namespace App\Support;

use NumberFormatter;

final class Money
{
    public static function format(int|float|string|null $amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency = strtoupper($currency ?: (string) config('localization.default_currency', 'USD'));
        $locale = str_replace('_', '-', $locale ?: app()->getLocale() ?: 'en');
        $value = (float) ($amount ?? 0);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($value, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $currency.' '.number_format($value, 2, '.', ',');
    }

    public static function currency(?string $currency): string
    {
        $code = strtoupper(trim((string) $currency));

        return preg_match('/^[A-Z]{3}$/', $code) === 1
            ? $code
            : (string) config('localization.default_currency', 'USD');
    }
}
