<?php

namespace App\Support;

use App\Services\PhaseThree\FxQuoteService;
use Illuminate\Validation\ValidationException;

/** Exact decimal conversion. Never derive money totals from floats. */
final class MinorMoney
{
    public static function digits(string $currency): int
    {
        return app(FxQuoteService::class)->minorDigits($currency);
    }

    public static function toMinor(string|int|float|null $amount, string $currency): int
    {
        $input = trim((string) ($amount ?? '0'));
        $digits = self::digits($currency);
        if (! preg_match('/^(-?)([0-9]{1,15})(?:\.([0-9]{1,3}))?$/D', $input, $match)) {
            throw ValidationException::withMessages(['amount' => 'Invalid monetary amount.']);
        }
        $fraction = $match[3] ?? '';
        if (strlen(rtrim(substr($fraction, $digits), '0')) > 0) {
            throw ValidationException::withMessages(['amount' => 'Unsupported fractional currency units.']);
        }
        $value = ((int) $match[2] * (10 ** $digits))
            + (int) str_pad(substr($fraction, 0, $digits), $digits, '0');
        return ($match[1] ?? '') === '-' ? -$value : $value;
    }

    public static function display(int $minor, string $currency): string
    {
        $digits = self::digits($currency);
        $scale = 10 ** $digits;
        $negative = $minor < 0 ? '-' : '';
        $absolute = abs($minor);
        return $negative.number_format(intdiv($absolute, $scale), 0, '.', ',')
            .($digits ? '.'.str_pad((string) ($absolute % $scale), $digits, '0', STR_PAD_LEFT) : '');
    }
}
