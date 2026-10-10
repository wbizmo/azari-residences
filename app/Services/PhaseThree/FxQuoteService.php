<?php

namespace App\Services\PhaseThree;

use App\Models\FxQuoteLock;
use App\Models\FxReferenceRate;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exact fixed-scale quote calculation. Never writes to an existing booking/payment. */
final class FxQuoteService
{
    private const DIGITS = ['JPY'=>0, 'KRW'=>0, 'KWD'=>3, 'BHD'=>3, 'OMR'=>3];

    public function minorDigits(string $currency): int
    {
        $currency = strtoupper($currency);
        if (! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw ValidationException::withMessages(['currency'=>'Invalid currency.']);
        }
        return self::DIGITS[$currency] ?? 2;
    }

    private function scaledRate(string $rate): int
    {
        if (! preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.([0-9]{1,8}))?$/D', $rate)) {
            throw ValidationException::withMessages(['rate'=>'Invalid fixed-point exchange rate.']);
        }
        [$integer,$fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $scaled = ((int) $integer * 100000000) + (int) str_pad($fraction, 8, '0');
        if ($scaled < 1) throw ValidationException::withMessages(['rate'=>'Rate must be positive.']);
        return $scaled;
    }

    public function convertMinor(int $amountMinor, string $from, string $to, string $rate): int
    {
        if ($amountMinor < 0) throw ValidationException::withMessages(['amount'=>'Amount must not be negative.']);
        $scaled = $this->scaledRate($rate);
        $sourceScale = 10 ** $this->minorDigits($from);
        $targetScale = 10 ** $this->minorDigits($to);
        $factor = $scaled * $targetScale;
        // Decline unusually large conversions rather than silently overflowing
        // to floats and violating money rounding or ledger reconciliation.
        if ($amountMinor > intdiv(PHP_INT_MAX - 100000000000, $factor)) {
            throw ValidationException::withMessages(['amount'=>'Conversion exceeds exact arithmetic limit.']);
        }
        $denominator = 100000000 * $sourceScale;
        return intdiv($amountMinor * $factor + intdiv($denominator, 2), $denominator);
    }

    /**
     * Claims one matching, unexpired FX snapshot at most once. Never authorizes
     * payment by itself; a gateway must explicitly support the charge currency.
     */
    public function consumeForCheckout(string $quoteId, int $userId,
        int $expectedBaseMinor, string $expectedChargeCurrency): FxQuoteLock
    {
        if (! config('reserva.fx.checkout_enabled', false)) {
            throw ValidationException::withMessages(['fx'=>'Multi-currency checkout has not been certified.']);
        }
        return DB::transaction(function () use ($quoteId, $userId, $expectedBaseMinor,
            $expectedChargeCurrency): FxQuoteLock {
            $quote = FxQuoteLock::query()->whereKey($quoteId)->lockForUpdate()->firstOrFail();
            if ((int) $quote->user_id !== $userId
                || (int) $quote->base_minor !== $expectedBaseMinor
                || ! hash_equals((string) $quote->quote_currency, strtoupper($expectedChargeCurrency))
                || $quote->expires_at->isPast() || $quote->consumed_at) {
                throw ValidationException::withMessages(['fx'=>'FX quote is stale, already claimed, or mismatched.']);
            }
            $quote->forceFill(['consumed_at'=>now()])->save();
            return $quote->fresh();
        }, 3);
    }

    public function lock(FxReferenceRate $rate, int $baseMinor, ?int $userId = null): FxQuoteLock
    {
        if ($rate->expires_at->isPast() || $rate->observed_at->isFuture()) {
            throw ValidationException::withMessages(['rate'=>'Verified exchange rate is stale or not yet effective.']);
        }
        $supported = array_keys(config('localization.supported_currencies', []));
        if (! in_array($rate->base_currency, $supported, true)
            || ! in_array($rate->quote_currency, $supported, true)) {
            throw ValidationException::withMessages(['currency'=>'Unsupported exchange rate pair.']);
        }
        return FxQuoteLock::query()->create([
            'id'=> (string) Str::uuid(),
            'user_id'=>$userId, 'fx_reference_rate_id'=>$rate->id,
            'base_currency'=>$rate->base_currency, 'quote_currency'=>$rate->quote_currency,
            'base_minor'=>$baseMinor,
            'quote_minor'=>$this->convertMinor($baseMinor, $rate->base_currency,
                $rate->quote_currency, (string) $rate->units_per_base),
            'locked_rate'=>$rate->units_per_base,
            'expires_at'=>min($rate->expires_at, now()->addMinutes(10)),
        ]);
    }
}
