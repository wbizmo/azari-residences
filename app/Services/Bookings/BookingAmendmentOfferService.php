<?php

namespace App\Services\Bookings;

use App\Models\AccommodationType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingAddOn;
use App\Models\BookingModificationRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RatePlan;
use App\Models\User;
use App\Services\Payments\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingAmendmentOfferService
{
    public function __construct(
        private readonly AzariAvailabilityEngine $availability,
        private readonly AzariPricingEngine $pricing,
        private readonly RefundService $refunds,
        private readonly BookingModificationService $modifications,
    ) {}

    /**
     * Produce an expiring quote for an already requested date change.
     * It is an offer, NOT inventory reserved and NOT customer approval.
     */
    public function offer(Booking $booking, BookingModificationRequest $request, User $staff): BookingModificationRequest
    {
        abort_unless($staff->hasPermission('bookings.edit'), 403);

        return DB::transaction(function () use ($booking, $request, $staff): BookingModificationRequest {
            [$locked, $change, $type] = $this->lock($booking, $request);

            if ($change->status !== 'pending' || ! in_array($change->type, ['date_change', 'add_extras'], true)) {
                throw ValidationException::withMessages(['change' => 'Only pending date or extras changes can be offered a quote.']);
            }
            $this->assertEligible($locked);
            if (! $this->modifications->policyAllows($locked, $change->type)) {
                throw ValidationException::withMessages(['change' => 'The booking is no longer eligible for this amendment.']);
            }

            $newStart = $change->type === 'date_change'
                ? $this->parseDate((string) data_get($change->requested_changes, 'check_in'))
                : $this->parseDate($locked->check_in->toDateString());
            $newEnd = $change->type === 'date_change'
                ? $this->parseDate((string) data_get($change->requested_changes, 'check_out'))
                : $this->parseDate($locked->check_out->toDateString());
            $ratePlan = $locked->rate_plan_id ? RatePlan::query()
                ->whereKey($locked->rate_plan_id)->where('accommodation_type_id', $type->getKey())->firstOrFail() : null;

            $this->availability->assertRules($locked->property, $newStart, $newEnd,
                (int) $locked->adults, (int) $locked->children, (int) $locked->rooms, $type, $ratePlan);
            $this->availability->lockInventoryRange($type, $newStart, $newEnd);
            if ($this->availability->availableQuantity($type, $newStart, $newEnd, $locked->getKey()) < (int) $locked->rooms) {
                throw ValidationException::withMessages(['check_in' => 'The requested new dates are no longer available.']);
            }
            if ($locked->voucher_id || (float) ($locked->discount_total ?? 0) > 0) {
                throw ValidationException::withMessages([
                    'change' => 'Voucher-priced bookings need a manual contract review before date repricing.',
                ]);
            }

            $selectedAddOns = $locked->addOns()->get()->mapWithKeys(
                fn ($addon) => [$addon->getKey() => (int) $addon->pivot->quantity]
            )->all();
            if ($change->type === 'add_extras') {
                $newIds = array_values(array_unique(array_map('intval',
                    (array) data_get($change->requested_changes, 'add_on_ids', []))));
                if ($newIds === [] || count($newIds) > 20 || in_array(0, $newIds, true)
                    || BookingAddOn::query()->whereIn('id', $newIds)->where('is_active', true)->count() !== count($newIds)) {
                    throw ValidationException::withMessages(['add_on_ids' => 'Choose one or more valid, available extras.']);
                }
                $changed = false;
                foreach ($newIds as $id) {
                    if (! isset($selectedAddOns[$id])) {
                        $selectedAddOns[$id] = 1;
                        $changed = true;
                    }
                }
                if (! $changed) {
                    throw ValidationException::withMessages(['add_on_ids' => 'These extras are already part of the booking.']);
                }
            }
            $quote = $this->pricing->quote($locked->property, $newStart, $newEnd,
                $selectedAddOns, $type, $ratePlan, (int) $locked->rooms);

            if (strtoupper((string) $locked->currency) !== strtoupper((string) $quote['currency'])) {
                throw ValidationException::withMessages(['change' => 'Currency changes require separate settlement approval.']);
            }
            $total = round((float) $quote['total'], 2);
            $snapshot = [
                'old_total' => round((float) $locked->total, 2),
                'new_total' => $total,
                'delta' => round($total - (float) $locked->total, 2),
                'currency' => strtoupper((string) $locked->currency),
                'old_check_in' => $locked->check_in->toDateString(),
                'old_check_out' => $locked->check_out->toDateString(),
                'new_check_in' => $newStart->toDateString(),
                'new_check_out' => $newEnd->toDateString(),
                'booking_updated_at' => $locked->updated_at?->toIso8601String(),
                'net_paid' => $locked->netPaidTotal(),
                'change_type' => $change->type,
                'selected_add_ons' => $selectedAddOns,
                'quote' => $quote,
            ];

            $change->forceFill([
                'status' => 'quoted',
                'price_quote' => $snapshot,
                'quote_expires_at' => now()->addMinutes(15),
                'reviewed_by' => $staff->getKey(),
                'reviewed_at' => now(),
            ])->save();

            AuditLog::record('booking.modification_offered', $change, [], [
                'booking_id' => $locked->getKey(),
                'old_total' => $snapshot['old_total'], 'new_total' => $total,
                'currency' => $snapshot['currency'],
            ], actorId: $staff->getKey());

            return $change->refresh();
        }, 5);
    }

    /**
     * Guest accepts an unchanged/reduced quote. Additional charges require a
     * separate verified amendment payment; never move dates on an IOU.
     */
    public function accept(Booking $booking, BookingModificationRequest $request, User $guest): BookingModificationRequest
    {
        abort_unless((int) $booking->user_id === (int) $guest->getKey(), 403);

        return DB::transaction(function () use ($booking, $request, $guest): BookingModificationRequest {
            [$locked, $change, $type] = $this->lock($booking, $request);
            abort_unless((int) $locked->user_id === (int) $guest->getKey(), 403);

            if ($change->status !== 'quoted' || ! $change->quote_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['change' => 'This offer has expired or was already used. Request a fresh quotation.']);
            }
            $this->assertEligible($locked);
            if (! in_array($change->type, ['date_change', 'add_extras'], true)
                || ! $this->modifications->policyAllows($locked, $change->type)) {
                throw ValidationException::withMessages(['change' => 'This booking is no longer eligible for changes.']);
            }
            $snapshot = $change->price_quote;
            if (! is_array($snapshot)
                || $locked->updated_at?->toIso8601String() !== ($snapshot['booking_updated_at'] ?? null)
                || round((float) $locked->total, 2) !== round((float) ($snapshot['old_total'] ?? -1), 2)
                || round($locked->netPaidTotal(), 2) !== round((float) ($snapshot['net_paid'] ?? -1), 2)
                || $locked->check_in->toDateString() !== ($snapshot['old_check_in'] ?? null)
                || $locked->check_out->toDateString() !== ($snapshot['old_check_out'] ?? null)) {
                throw ValidationException::withMessages(['change' => 'The original booking has changed. Request a fresh quotation.']);
            }

            $start = $this->parseDate((string) ($snapshot['new_check_in'] ?? ''));
            $end = $this->parseDate((string) ($snapshot['new_check_out'] ?? ''));
            $ratePlan = $locked->rate_plan_id ? RatePlan::query()
                ->whereKey($locked->rate_plan_id)->where('accommodation_type_id', $type->getKey())->firstOrFail() : null;
            $this->availability->assertRules($locked->property, $start, $end,
                (int) $locked->adults, (int) $locked->children, (int) $locked->rooms, $type, $ratePlan);
            $this->availability->lockInventoryRange($type, $start, $end);
            if ($this->availability->availableQuantity($type, $start, $end, $locked->getKey()) < (int) $locked->rooms) {
                throw ValidationException::withMessages(['change' => 'These dates were booked by another guest. Request a fresh quotation.']);
            }

            $selectedAddOns = $locked->addOns()->get()->mapWithKeys(
                fn ($addon) => [$addon->getKey() => (int) $addon->pivot->quantity]
            )->all();
            if ($change->type === 'add_extras') {
                if (($snapshot['change_type'] ?? null) !== 'add_extras'
                    || ! is_array($snapshot['selected_add_ons'] ?? null)) {
                    throw ValidationException::withMessages(['change' => 'The extras quotation is incomplete.']);
                }
                $newIds = array_values(array_unique(array_map('intval',
                    (array) data_get($change->requested_changes, 'add_on_ids', []))));
                if ($newIds === [] || BookingAddOn::query()->whereIn('id', $newIds)
                    ->where('is_active', true)->count() !== count($newIds)) {
                    throw ValidationException::withMessages(['add_on_ids' => 'An extra is no longer available.']);
                }
                foreach ($newIds as $id) {
                    if (isset($selectedAddOns[$id])) {
                        throw ValidationException::withMessages(['change' => 'An extra has already been added. Request a new quotation.']);
                    }
                }
                $selectedAddOns = array_map('intval', $snapshot['selected_add_ons']);
            }
            $quote = $this->pricing->quote($locked->property, $start, $end,
                $selectedAddOns, $type, $ratePlan, (int) $locked->rooms);
            if (round((float) $quote['total'], 2) !== round((float) $snapshot['new_total'], 2)
                || strtoupper((string) $quote['currency']) !== ($snapshot['currency'] ?? '')) {
                throw ValidationException::withMessages(['change' => 'Rates changed since the offer. Request a new quotation.']);
            }
            $difference = round((float) $quote['total'] - (float) $locked->total, 2);
            $topup = null;
            if ($difference > 0.009) {
                // A verified provider charge is unallocated until the guest
                // accepts THIS quote and the new dates pass the final lock.
                $topup = $change->payment_id ? Payment::query()->whereKey($change->payment_id)
                    ->when(DB::connection()->getDriverName() !== 'sqlite',
                        fn (Builder $q) => $q->lockForUpdate())->first() : null;
                if (! $topup || $topup->status !== 'successful_excess'
                    || ! $topup->verified_at
                    || $topup->payment_kind !== 'amendment'
                    || (int) $topup->booking_id !== (int) $locked->getKey()
                    || (int) $topup->user_id !== (int) $guest->getKey()
                    || strtoupper((string) $topup->currency) !== strtoupper((string) $quote['currency'])
                    || abs((float) $topup->amount - $difference) >= 0.01) {
                    throw ValidationException::withMessages([
                        'payment' => 'The exact additional payment must be independently verified before changing dates.',
                    ]);
                }
                if (\App\Models\Refund::query()->where('payment_id', $topup->getKey())
                    ->whereIn('status', ['requested', 'processing', 'reconciliation_required', 'successful'])->exists()) {
                    throw ValidationException::withMessages([
                        'payment' => 'The additional payment is already in a refund workflow and cannot be allocated to this change.',
                    ]);
                }
            }

            $refundDue = max(0.0, round($locked->netPaidTotal() - (float) $quote['total'], 2));
            if ($refundDue > 0.009) {
                // Reserve refunds atomically; requests are NOT yet provider-settled.
                $remaining = $refundDue;
                foreach ($locked->payments()->where('status', Payment::SUCCESSFUL)->orderBy('id')->get() as $payment) {
                    $alreadyReserved = (float) $payment->refunds()
                        ->whereIn('status', ['requested', 'processing', 'successful'])->sum('amount');
                    $amount = min($remaining, max(0, round((float) $payment->amount - $alreadyReserved, 2)));
                    if ($amount > 0.009) {
                        $this->refunds->request($payment, $amount, $guest->getKey(),
                            'Price reduction on accepted booking amendment '.$change->reference,
                            hash('sha256', 'amendment-refund|'.$change->getKey().'|'.$payment->getKey()));
                        $remaining = round($remaining - $amount, 2);
                    }
                }
                if ($remaining > 0.009) {
                    throw ValidationException::withMessages([
                        'refund' => 'The payment records cannot safely reserve the required refund. Contact support.',
                    ]);
                }
            }

            $locked->forceFill([
                'check_in' => $start, 'check_out' => $end,
                'nights' => $quote['nights'], 'nightly_rate' => $quote['nightly_rate'],
                'subtotal' => $quote['subtotal'], 'fee_total' => $quote['fee_total'],
                'add_on_total' => $quote['add_on_total'], 'discount_total' => $quote['discount_total'],
                'tax_rate' => $quote['tax_rate'], 'tax_total' => $quote['tax_total'],
                'total' => $quote['total'], 'pricing_snapshot' => $quote, 'modified_at' => now(),
            ])->save();

            // Pivot line totals must follow the new quote, particularly for
            // per-night extras when dates change and for newly purchased add-ons.
            $pivot = [];
            foreach (($quote['addons'] ?? []) as $addonLine) {
                $pivot[(int) $addonLine['id']] = [
                    'quantity' => (int) $addonLine['quantity'],
                    'unit_price' => (float) $addonLine['unit_price'],
                    'line_total' => (float) $addonLine['line_total'],
                ];
            }
            $locked->addOns()->sync($pivot);

            if ($topup) {
                $topup->forceFill([
                    'status' => Payment::SUCCESSFUL,
                    'administrative_note' => 'Amendment top-up allocated after customer consent and final inventory check.',
                ])->save();
                app(\App\Services\Owners\OwnerEarningsService::class)->creditForPayment($topup->refresh());
            }

            $change->forceFill(['status' => 'approved', 'accepted_at' => now()])->save();
            AuditLog::record('booking.modification_applied', $change,
                ['old_dates' => [$snapshot['old_check_in'], $snapshot['old_check_out']]],
                ['new_dates' => [$start->toDateString(), $end->toDateString()]],
                ['old_total' => $snapshot['old_total'], 'new_total' => $quote['total'],
                 'refund_requested' => $refundDue, 'currency' => $quote['currency']],
                $guest->getKey());

            return $change->refresh();
        }, 5);
    }

    /** Locks in the same property → room type → booking → amendment order as inventory holds. */
    private function lock(Booking $booking, BookingModificationRequest $request): array
    {
        $forUpdate = fn (Builder $query) => DB::connection()->getDriverName() === 'sqlite'
            ? $query : $query->lockForUpdate();
        $property = Property::query()->whereKey($booking->property_id)
            ->when(DB::connection()->getDriverName() !== 'sqlite', $forUpdate)->firstOrFail();
        $type = AccommodationType::query()->whereKey($booking->accommodation_type_id)
            ->where('property_id', $property->getKey())
            ->when(DB::connection()->getDriverName() !== 'sqlite', $forUpdate)->firstOrFail();
        $locked = Booking::query()->whereKey($booking->getKey())->where('property_id', $property->getKey())
            ->when(DB::connection()->getDriverName() !== 'sqlite', $forUpdate)->firstOrFail();
        $locked->setRelation('property', $property);
        $change = BookingModificationRequest::query()->whereKey($request->getKey())
            ->where('booking_id', $locked->getKey())->where('user_id', $locked->user_id)
            ->when(DB::connection()->getDriverName() !== 'sqlite', $forUpdate)->firstOrFail();
        return [$locked, $change, $type];
    }

    private function assertEligible(Booking $booking): void
    {
        if (! in_array($booking->status, ['paid', 'confirmed'], true)
            || $booking->checked_in_at || $booking->cancelled_at
            || ! $booking->check_in?->isFuture()) {
            throw ValidationException::withMessages(['change' => 'Only active, unstarted confirmed stays can change dates.']);
        }
    }

    private function parseDate(string $date): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages(['check_in' => 'Select a valid date in YYYY-MM-DD format.']);
        }
        try {
            $value = CarbonImmutable::createFromFormat('!Y-m-d', $date);
            if ($value && $value->format('Y-m-d') === $date) {
                return $value;
            }
        } catch (\Throwable) {
        }
        throw ValidationException::withMessages(['check_in' => 'Select a valid calendar date.']);
    }
}
