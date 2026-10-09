<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * External provider statement reconciliation: independent evidence in,
 * read-only exception counts out. Never changes payment/refund state.
 *
 * Input columns: type,reference,currency,amount,status
 *  type = payment or refund; status = settled.
 *  amount = positive decimal amount in currency major units.
 */
class ProviderStatementReconciliationService
{
    /** @return array<string,mixed> */
    public function compare(string $provider, string $csvPath): array
    {
        $provider = strtolower(trim($provider));
        if (! preg_match('/^[a-z0-9_-]{2,40}$/', $provider) || ! is_file($csvPath) || ! is_readable($csvPath)) {
            throw new \InvalidArgumentException('A provider and readable local settlement file are required.');
        }
        if (filesize($csvPath) === false || filesize($csvPath) > 25_000_000) {
            throw new \InvalidArgumentException('Settlement statement exceeds the maximum permitted size.');
        }
        $handle = fopen($csvPath, 'rb');
        if (! is_resource($handle)) {
            throw new \RuntimeException('Settlement file could not be opened.');
        }
        try {
            $columns = fgetcsv($handle, 2048, ',', '"', '\\');
            if ($columns !== ['type', 'reference', 'currency', 'amount', 'status']) {
                throw new \InvalidArgumentException('Expected exact CSV headers: type,reference,currency,amount,status.');
            }

            $exceptions = [];
            $totals = [];
            $seen = [];
            $rows = 0;

            while (($cells = fgetcsv($handle, 2048, ',', '"', '\\')) !== false) {
                $rows++;
                if ($rows > 100000) {
                    throw new \InvalidArgumentException('Statement row limit exceeded.');
                }
                if (count($cells) !== 5) {
                    throw new \InvalidArgumentException('Malformed settlement statement row.');
                }
                [$type, $reference, $currency, $amountText, $status] = array_map('trim', $cells);
                $currency = strtoupper($currency);
                if (! in_array($type, ['payment', 'refund'], true)
                    || ! preg_match('/^[A-Z]{3}$/', $currency)
                    || $reference === '' || strlen($reference) > 190
                    || ! preg_match('/^(?:0|[1-9][0-9]{0,11})\.[0-9]{2}$/', $amountText)
                    || ! in_array($status, ['settled', 'reversed'], true)) {
                    throw new \InvalidArgumentException('Invalid statement row. Input must be normalized to major currency units.');
                }

                $key = implode('|', [$type, $currency, $reference]);
                if (isset($seen[$key])) {
                    $exceptions[] = ['row' => $rows, 'reason' => 'duplicate_statement_reference', 'type' => $type];
                    continue;
                }
                $seen[$key] = true;

                // A reversed status is NOT confirmed settlement. Surface it
                // rather than subtracting a potentially unsettled amount.
                if ($status !== 'settled') {
                    $exceptions[] = ['row' => $rows, 'reason' => 'provider_reversal_requires_review', 'type' => $type];
                    continue;
                }

                $model = $type === 'payment' ? Payment::class : Refund::class;
                $local = $model::query()
                    ->where('provider', $provider)
                    ->where('provider_reference', $reference)
                    ->limit(2)->get();

                if ($local->count() !== 1) {
                    $exceptions[] = [
                        'row' => $rows,
                        'reason' => $local->isEmpty() ? 'provider_reference_unmatched' : 'provider_reference_ambiguous',
                        'type' => $type,
                    ];
                    continue;
                }

                $record = $local->first();
                $expected = $type === 'payment' ? Payment::SUCCESSFUL : 'successful';
                if ($record->status !== $expected) {
                    $exceptions[] = ['row' => $rows, 'reason' => 'not_successfully_recorded_locally', 'type' => $type];
                    continue;
                }
                if (strtoupper((string) $record->currency) !== $currency
                    || $this->minor((string) $record->amount) !== $this->minor($amountText)) {
                    $exceptions[] = ['row' => $rows, 'reason' => 'amount_or_currency_mismatch', 'type' => $type];
                    continue;
                }

                $minor = $this->minor($amountText);
                $totals[$currency] ??= ['charges_minor' => 0, 'refunds_minor' => 0, 'matched_payments' => 0, 'matched_refunds' => 0];
                if ($type === 'payment') {
                    $totals[$currency]['charges_minor'] += $minor;
                    $totals[$currency]['matched_payments']++;
                } else {
                    $totals[$currency]['refunds_minor'] += $minor;
                    $totals[$currency]['matched_refunds']++;
                }
            }

            foreach ($totals as $currency => &$amounts) {
                $amounts['net_minor_before_fees'] = $amounts['charges_minor'] - $amounts['refunds_minor'];
            }
            unset($amounts);

            return [
                'provider' => $provider,
                'statement_rows' => $rows,
                'matched_by_currency' => $totals,
                'exception_count' => count($exceptions),
                'exceptions' => $exceptions,
                'statement_reconciled' => count($exceptions) === 0 && $rows > 0,
                'notes' => 'Local reference/amount match only. Does not prove actual bank payout, provider fees, chargeback finality or absence of omitted statement rows.',
            ];
        } finally {
            fclose($handle);
        }
    }

    private function minor(string $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', $normalized, 2);
        return ((int) $whole * 100) + (int) $fraction;
    }
}
