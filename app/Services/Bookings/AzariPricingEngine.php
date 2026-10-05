<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\BookingAddOn;
use App\Models\DailyRate;
use App\Models\InventoryDate;
use App\Models\PricingPromotion;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\RatePlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AzariPricingEngine
{
    public function quote(
        Property $property,
        CarbonInterface $in,
        CarbonInterface $out,
        array $selected = [],
        ?AccommodationType $accommodationType = null,
        ?RatePlan $ratePlan = null,
        int $quantity = 1
    ): array {
        $start = CarbonImmutable::parse($in->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($out->toDateString())->startOfDay();
        $quantity = max(1, $quantity);

        $accommodationType ??= $this->primaryAccommodationType($property);
        $ratePlan ??= $accommodationType ? $this->primaryRatePlan($accommodationType) : null;

        $dates = collect();
        for ($cursor = $start; $cursor->lessThan($end); $cursor = $cursor->addDay()) {
            $dates->push($cursor);
        }

        $nights = $dates->count();

        $dailyRates = $this->dailyRates($accommodationType, $ratePlan, $start, $end);
        $inventoryRates = $this->inventoryRates($accommodationType, $start, $end);
        $seasonalRates = $this->seasonalRates($property, $start, $end);
        $rules = $this->pricingRules($property, $accommodationType, $ratePlan);

        $breakdown = $dates->map(function (CarbonImmutable $date) use (
            $property,
            $accommodationType,
            $ratePlan,
            $dailyRates,
            $inventoryRates,
            $seasonalRates,
            $rules,
            $nights,
            $quantity
        ): array {
            $rate = $this->baseRate($property, $accommodationType);
            $source = 'base';

            $season = $this->matchingSeason($seasonalRates, $date);
            if ($season) {
                $rate = (float) $season->nightly_rate;
                $source = 'seasonal';
            } elseif (
                in_array($date->dayOfWeekIso, [6, 7], true)
                && $this->weekendRate($property, $accommodationType) > 0
            ) {
                $rate = $this->weekendRate($property, $accommodationType);
                $source = 'weekend';
            }

            $inventoryOverride = $inventoryRates->get($date->toDateString());
            if ($inventoryOverride !== null) {
                $rate = (float) $inventoryOverride;
                $source = 'inventory_override';
            }

            $daily = $dailyRates->get($date->toDateString());
            if ($daily) {
                $rate = (float) $daily->amount;
                $source = 'daily_rate';
            }

            [$rate, $ruleApplications] = $this->applyRules($rate, $rules, $date, $nights);

            $beforeRatePlan = $rate;
            $rate = $this->applyRatePlanAdjustment($rate, $ratePlan);

            return [
                'date' => $date->toDateString(),
                'unit_rate' => round(max(0, $rate), 2),
                'quantity' => $quantity,
                'line_total' => round(max(0, $rate) * $quantity, 2),
                'source' => $source,
                'pricing_rules' => $ruleApplications,
                'rate_plan_adjustment' => round($rate - $beforeRatePlan, 2),
            ];
        })->values()->all();

        $subtotalBeforePromotion = round(array_sum(array_column($breakdown, 'line_total')), 2);

        [$discountTotal, $discounts] = $this->promotionDiscount(
            $property,
            $accommodationType,
            $ratePlan,
            $start,
            $end,
            $nights,
            $subtotalBeforePromotion
        );

        $subtotal = round(max(0, $subtotalBeforePromotion - $discountTotal), 2);

        $cleaningFee = round(
            (float) ($accommodationType?->cleaning_fee ?? $property->cleaning_fee ?? 0) * $quantity,
            2
        );

        $serviceCharge = round((float) (
            $accommodationType?->service_charge
            ?? $property->service_charge
            ?? $property->service_fee
            ?? config('azari.booking.default_service_fee', 0)
        ), 2);

        $feeBreakdown = [];
        if ($cleaningFee > 0) {
            $feeBreakdown[] = ['name' => 'Cleaning fee', 'amount' => $cleaningFee];
        }
        if ($serviceCharge > 0) {
            $feeBreakdown[] = ['name' => 'Service charge', 'amount' => $serviceCharge];
        }

        $feeTotal = round($cleaningFee + $serviceCharge, 2);

        [$addons, $addonTotal] = $this->addOns($selected, $nights);

        $taxRate = (float) (
            $accommodationType?->tax_rate
            ?? $property->tax_rate
            ?? config('azari.booking.default_tax_rate', 0)
        );

        $taxable = round($subtotal + $feeTotal + $addonTotal, 2);
        $tax = round($taxable * ($taxRate / 100), 2);
        $total = round($taxable + $tax, 2);

        $securityDeposit = round(
            (float) ($accommodationType?->security_deposit ?? $property->security_deposit ?? 0),
            2
        );

        $currency = strtoupper((string) (
            $accommodationType?->currency
            ?? $property->currency
            ?? config('azari.currency', 'USD')
        ));

        return [
            'currency' => $currency,
            'quantity' => $quantity,
            'nights' => $nights,
            'nightly_rate' => $nights > 0
                ? round($subtotalBeforePromotion / ($nights * $quantity), 2)
                : 0,
            'nightly_breakdown' => $breakdown,
            'subtotal_before_discount' => $subtotalBeforePromotion,
            'discounts' => $discounts,
            'discount_total' => $discountTotal,
            'subtotal' => $subtotal,
            'fee_breakdown' => $feeBreakdown,
            'fee_total' => $feeTotal,
            'add_ons' => $addons,
            'add_on_total' => $addonTotal,
            'tax_rate' => $taxRate,
            'tax_total' => $tax,
            'security_deposit' => $securityDeposit,
            'total' => $total,
            'accommodation_type_id' => $accommodationType?->getKey(),
            'accommodation_type_name' => $accommodationType?->name,
            'rate_plan_id' => $ratePlan?->getKey(),
            'rate_plan_name' => $ratePlan?->publicLabel(),
            'policy' => $this->policySnapshot($ratePlan),
        ];
    }

    private function primaryAccommodationType(Property $property): ?AccommodationType
    {
        if (! Schema::hasTable('accommodation_types')) {
            return null;
        }

        return AccommodationType::query()
            ->where('property_id', $property->getKey())
            ->where('is_active', true)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function primaryRatePlan(AccommodationType $type): ?RatePlan
    {
        if (! Schema::hasTable('rate_plans')) {
            return null;
        }

        return RatePlan::query()
            ->with(['cancellationPolicy', 'paymentPolicy'])
            ->where('accommodation_type_id', $type->getKey())
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function baseRate(Property $property, ?AccommodationType $type): float
    {
        return max(0, (float) ($type?->base_rate ?? $property->nightly_rate ?? 0));
    }

    private function weekendRate(Property $property, ?AccommodationType $type): float
    {
        return max(0, (float) ($type?->weekend_rate ?? $property->weekend_rate ?? 0));
    }

    private function dailyRates(
        ?AccommodationType $type,
        ?RatePlan $ratePlan,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection {
        if (! $type || ! Schema::hasTable('daily_rates')) {
            return collect();
        }

        return DailyRate::query()
            ->where('accommodation_type_id', $type->getKey())
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->where(function (Builder $query) use ($ratePlan): void {
                $query->whereNull('rate_plan_id');
                if ($ratePlan) {
                    $query->orWhere('rate_plan_id', $ratePlan->getKey());
                }
            })
            ->where('stop_sell', false)
            ->get()
            ->groupBy(fn (DailyRate $row) => $row->date->toDateString())
            ->map(function (Collection $rows) use ($ratePlan): ?DailyRate {
                if ($ratePlan) {
                    $specific = $rows->first(fn (DailyRate $row) =>
                        (int) $row->rate_plan_id === (int) $ratePlan->getKey()
                    );

                    if ($specific) {
                        return $specific;
                    }
                }

                return $rows->first(fn (DailyRate $row) => $row->rate_plan_id === null);
            })
            ->filter();
    }

    private function inventoryRates(
        ?AccommodationType $type,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection {
        if (! $type || ! Schema::hasTable('inventory_dates')) {
            return collect();
        }

        return InventoryDate::query()
            ->where('accommodation_type_id', $type->getKey())
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->whereNotNull('price_override')
            ->get(['date', 'price_override'])
            ->mapWithKeys(fn (InventoryDate $row) => [
                $row->date->toDateString() => (float) $row->price_override,
            ]);
    }

    private function seasonalRates(
        Property $property,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection {
        if (! Schema::hasTable('seasonal_prices')) {
            return collect();
        }

        return collect(DB::table('seasonal_prices')
            ->where('property_id', $property->getKey())
            ->whereDate('starts_on', '<', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->orderByDesc('starts_on')
            ->get());
    }

    private function matchingSeason(Collection $seasonalRates, CarbonImmutable $date): ?object
    {
        return $seasonalRates->first(function (object $season) use ($date): bool {
            return $date->betweenIncluded(
                CarbonImmutable::parse($season->starts_on),
                CarbonImmutable::parse($season->ends_on)
            );
        });
    }

    private function pricingRules(
        Property $property,
        ?AccommodationType $type,
        ?RatePlan $ratePlan
    ): Collection {
        if (! Schema::hasTable('pricing_rules')) {
            return collect();
        }

        return PricingRule::query()
            ->where('property_id', $property->getKey())
            ->where('is_active', true)
            ->where(function (Builder $query) use ($type): void {
                $query->whereNull('accommodation_type_id');
                if ($type) {
                    $query->orWhere('accommodation_type_id', $type->getKey());
                }
            })
            ->where(function (Builder $query) use ($ratePlan): void {
                $query->whereNull('rate_plan_id');
                if ($ratePlan) {
                    $query->orWhere('rate_plan_id', $ratePlan->getKey());
                }
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    private function applyRules(
        float $rate,
        Collection $rules,
        CarbonImmutable $date,
        int $nights
    ): array {
        $applications = [];

        foreach ($rules as $rule) {
            if ($rule->starts_on && $date->lessThan(CarbonImmutable::parse($rule->starts_on))) {
                continue;
            }

            if ($rule->ends_on && $date->greaterThan(CarbonImmutable::parse($rule->ends_on))) {
                continue;
            }

            if ($rule->minimum_stay && $nights < (int) $rule->minimum_stay) {
                continue;
            }

            if ($rule->maximum_stay && $nights > (int) $rule->maximum_stay) {
                continue;
            }

            $days = collect($rule->days_of_week ?? [])->map(fn ($day) => strtolower((string) $day));
            if ($days->isNotEmpty()) {
                $matchesDay = $days->contains((string) $date->dayOfWeekIso)
                    || $days->contains(strtolower($date->format('D')))
                    || $days->contains(strtolower($date->format('l')));

                if (! $matchesDay) {
                    continue;
                }
            }

            $before = $rate;
            $kind = strtolower((string) (
                ($rule->adjustment_type ?? 'auto') !== 'auto'
                    ? $rule->adjustment_type
                    : $rule->rule_type
            ));

            $amount = (float) ($rule->amount ?? 0);
            $percentage = (float) ($rule->percentage ?? 0);

            if (in_array($kind, ['fixed', 'fixed_rate', 'override', 'rate'], true) && $amount >= 0) {
                $rate = $amount;
            } elseif (in_array($kind, ['discount', 'decrease', 'markdown'], true)) {
                if ($percentage > 0) {
                    $rate -= $rate * ($percentage / 100);
                }
                if ($amount > 0) {
                    $rate -= $amount;
                }
            } elseif (in_array($kind, ['increase', 'surcharge', 'markup'], true)) {
                if ($percentage > 0) {
                    $rate += $rate * ($percentage / 100);
                }
                if ($amount > 0) {
                    $rate += $amount;
                }
            } elseif ($percentage !== 0.0) {
                $rate += $rate * ($percentage / 100);
            } elseif ($amount !== 0.0) {
                $rate += $amount;
            }

            $rate = max(0, $rate);

            if (abs($rate - $before) >= 0.005) {
                $applications[] = [
                    'id' => $rule->getKey(),
                    'name' => $rule->name,
                    'before' => round($before, 2),
                    'after' => round($rate, 2),
                ];
            }
        }

        return [$rate, $applications];
    }

    private function applyRatePlanAdjustment(float $rate, ?RatePlan $ratePlan): float
    {
        if (! $ratePlan) {
            return max(0, $rate);
        }

        $value = (float) $ratePlan->pricing_adjustment;

        return max(0, match ($ratePlan->pricing_adjustment_type) {
            'fixed' => $rate + $value,
            'percentage' => $rate + ($rate * ($value / 100)),
            default => $rate,
        });
    }

    private function promotionDiscount(
        Property $property,
        ?AccommodationType $type,
        ?RatePlan $ratePlan,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $nights,
        float $subtotal
    ): array {
        if ($subtotal <= 0 || ! Schema::hasTable('pricing_promotions')) {
            return [0.0, []];
        }

        $candidates = PricingPromotion::query()
            ->bookableNow()
            ->where(function (Builder $query) use ($property): void {
                $query->whereNull('property_id')->orWhere('property_id', $property->getKey());
            })
            ->where(function (Builder $query) use ($type): void {
                $query->whereNull('accommodation_type_id');
                if ($type) {
                    $query->orWhere('accommodation_type_id', $type->getKey());
                }
            })
            ->where(function (Builder $query) use ($ratePlan): void {
                $query->whereNull('rate_plan_id');
                if ($ratePlan) {
                    $query->orWhere('rate_plan_id', $ratePlan->getKey());
                }
            })
            ->where(function (Builder $query) use ($end): void {
                $query->whereNull('stay_starts_on')->orWhereDate('stay_starts_on', '<', $end->toDateString());
            })
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('stay_ends_on')->orWhereDate('stay_ends_on', '>=', $start->toDateString());
            })
            ->where(function (Builder $query) use ($nights): void {
                $query->whereNull('minimum_nights')->orWhere('minimum_nights', '<=', $nights);
            })
            ->where(function (Builder $query) use ($nights): void {
                $query->whereNull('maximum_nights')->orWhere('maximum_nights', '>=', $nights);
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            return [0.0, []];
        }

        $exclusive = $candidates->firstWhere('is_stackable', false);
        $promotions = $exclusive ? collect([$exclusive]) : $candidates;

        $discountTotal = 0.0;
        $applied = [];

        foreach ($promotions as $promotion) {
            $base = max(0, $subtotal - $discountTotal);

            $discount = $promotion->discount_type === 'fixed'
                ? (float) $promotion->discount_value
                : $base * ((float) $promotion->discount_value / 100);

            if ($promotion->maximum_discount !== null) {
                $discount = min($discount, (float) $promotion->maximum_discount);
            }

            $discount = round(min($base, max(0, $discount)), 2);

            if ($discount <= 0) {
                continue;
            }

            $discountTotal = round($discountTotal + $discount, 2);
            $applied[] = [
                'id' => $promotion->getKey(),
                'name' => $promotion->name,
                'amount' => $discount,
            ];
        }

        return [min($subtotal, $discountTotal), $applied];
    }

    private function addOns(array $selected, int $nights): array
    {
        if ($selected === []) {
            return [[], 0.0];
        }

        $addons = [];
        $addonTotal = 0.0;

        BookingAddOn::query()
            ->whereIn('id', array_keys($selected))
            ->where('is_active', true)
            ->get()
            ->each(function (BookingAddOn $addon) use ($selected, $nights, &$addons, &$addonTotal): void {
                $qty = max(0, (int) ($selected[$addon->getKey()] ?? 0));

                if ($qty < 1) {
                    return;
                }

                $multiplier = match ($addon->pricing_type) {
                    'per_night' => max(1, $nights),
                    'per_quantity_per_night' => max(1, $nights) * $qty,
                    default => $qty,
                };

                $line = round((float) $addon->price * $multiplier, 2);
                $addonTotal = round($addonTotal + $line, 2);

                $addons[] = [
                    'id' => $addon->getKey(),
                    'name' => $addon->name,
                    'quantity' => $qty,
                    'unit_price' => (float) $addon->price,
                    'line_total' => $line,
                ];
            });

        return [$addons, $addonTotal];
    }

    public function policySnapshot(?RatePlan $ratePlan): array
    {
        if (! $ratePlan) {
            return [];
        }

        $ratePlan->loadMissing(['cancellationPolicy', 'paymentPolicy']);

        return [
            'rate_plan' => [
                'id' => $ratePlan->getKey(),
                'name' => $ratePlan->name,
                'code' => $ratePlan->code,
                'is_refundable' => (bool) $ratePlan->is_refundable,
                'meal_plan' => $ratePlan->meal_plan,
                'inclusions' => $ratePlan->inclusions ?? [],
            ],
            'cancellation' => $ratePlan->cancellationPolicy ? [
                'id' => $ratePlan->cancellationPolicy->getKey(),
                'name' => $ratePlan->cancellationPolicy->name,
                'policy_type' => $ratePlan->cancellationPolicy->policy_type,
                'free_cancel_hours' => $ratePlan->cancellationPolicy->free_cancel_hours,
                'fee_percentage' => (float) $ratePlan->cancellationPolicy->fee_percentage,
                'fee_amount' => (float) $ratePlan->cancellationPolicy->fee_amount,
                'charge_first_night' => (bool) $ratePlan->cancellationPolicy->charge_first_night,
                'no_show_policy' => $ratePlan->cancellationPolicy->no_show_policy,
            ] : null,
            'payment' => $ratePlan->paymentPolicy ? [
                'id' => $ratePlan->paymentPolicy->getKey(),
                'name' => $ratePlan->paymentPolicy->name,
                'payment_type' => $ratePlan->paymentPolicy->payment_type,
                'deposit_type' => $ratePlan->paymentPolicy->deposit_type,
                'deposit_value' => $ratePlan->paymentPolicy->deposit_value !== null
                    ? (float) $ratePlan->paymentPolicy->deposit_value
                    : null,
                'balance_due_days_before_arrival' => $ratePlan->paymentPolicy->balance_due_days_before_arrival,
            ] : null,
        ];
    }
}
