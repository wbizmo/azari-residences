<?php

namespace App\Services\Communication;

use InvalidArgumentException;

final class PhoneNumberNormalizer
{
    public function normalize(?string $value, ?string $defaultCountryCode = null): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        $raw = preg_replace('/(?:ext\.?|x)\s*\d+$/i', '', $raw) ?? $raw;
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Enter a valid phone number.');
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        if (! $hasPlus) {
            $country = preg_replace('/\D+/', '', $defaultCountryCode ?: config('services.twilio.default_country_code', '234')) ?? '234';
            if (str_starts_with($digits, '0')) {
                $digits = $country.substr($digits, 1);
            } elseif (! str_starts_with($digits, $country)) {
                $digits = $country.$digits;
            }
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Enter a valid international phone number.');
        }

        return '+'.$digits;
    }

    public function whatsapp(?string $value): ?string
    {
        $normal = $this->normalize($value);
        return $normal ? 'whatsapp:'.$normal : null;
    }
}
