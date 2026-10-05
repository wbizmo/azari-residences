@php
    $cancel = $ratePlan->cancellationPolicy;
    $payment = $ratePlan->paymentPolicy;
@endphp
<div class="reserva-commercial-grid">
    <label>Name<input name="name" value="{{ old('name', $ratePlan->name) }}" required></label>
    <label>Code<input name="code" value="{{ old('code', $ratePlan->code) }}"></label>
    <label>Price adjustment
        <select name="pricing_adjustment_type" required>
            @foreach(['none'=>'None','fixed'=>'Fixed amount','percentage'=>'Percentage'] as $value=>$label)
                <option value="{{ $value }}" @selected(old('pricing_adjustment_type', $ratePlan->pricing_adjustment_type ?: 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Adjustment value<input name="pricing_adjustment" type="number" step="0.0001" value="{{ old('pricing_adjustment', $ratePlan->pricing_adjustment ?? 0) }}"></label>
    <label>Meal plan<input name="meal_plan" value="{{ old('meal_plan', $ratePlan->meal_plan) }}" placeholder="e.g. Breakfast included"></label>
    <label>Minimum stay<input name="minimum_stay" type="number" min="1" max="730" value="{{ old('minimum_stay', $ratePlan->minimum_stay) }}"></label>
    <label>Maximum stay<input name="maximum_stay" type="number" min="1" max="730" value="{{ old('maximum_stay', $ratePlan->maximum_stay) }}"></label>
    <label>Minimum advance days<input name="minimum_advance_days" type="number" min="0" max="1095" value="{{ old('minimum_advance_days', $ratePlan->minimum_advance_days) }}"></label>
    <label>Maximum advance days<input name="maximum_advance_days" type="number" min="0" max="1095" value="{{ old('maximum_advance_days', $ratePlan->maximum_advance_days) }}"></label>
    <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $ratePlan->sort_order ?? 0) }}"></label>

    <label>Cancellation policy name<input name="cancellation_name" value="{{ old('cancellation_name', $cancel?->name) }}"></label>
    <label>Cancellation type
        <select name="cancellation_type">
            @foreach(['property_default'=>'Property default','flexible'=>'Flexible','non_refundable'=>'Non-refundable'] as $value=>$label)
                <option value="{{ $value }}" @selected(old('cancellation_type', $cancel?->policy_type ?: 'property_default') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Free cancellation hours<input name="free_cancel_hours" type="number" min="0" value="{{ old('free_cancel_hours', $cancel?->free_cancel_hours) }}"></label>
    <label>Cancellation fee (%)<input name="cancellation_fee_percentage" type="number" min="0" max="100" step="0.001" value="{{ old('cancellation_fee_percentage', $cancel?->fee_percentage ?? 0) }}"></label>
    <label>Cancellation fixed fee<input name="cancellation_fee_amount" type="number" min="0" step="0.01" value="{{ old('cancellation_fee_amount', $cancel?->fee_amount ?? 0) }}"></label>
    <label>No-show policy
        <select name="no_show_policy">
            @foreach(['same_as_cancellation'=>'Same as cancellation','first_night'=>'First night','full_stay'=>'Full stay'] as $value=>$label)
                <option value="{{ $value }}" @selected(old('no_show_policy', $cancel?->no_show_policy ?: 'same_as_cancellation') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label>Payment policy name<input name="payment_name" value="{{ old('payment_name', $payment?->name) }}"></label>
    <label>Payment type
        <select name="payment_type" required>
            @foreach(['full_prepayment'=>'Full prepayment','deposit'=>'Deposit + balance','pay_later'=>'Pay later','pay_at_property'=>'Pay at property'] as $value=>$label)
                <option value="{{ $value }}" @selected(old('payment_type', $payment?->payment_type ?: 'full_prepayment') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Deposit type
        <select name="deposit_type">
            <option value="">Not applicable</option>
            <option value="fixed" @selected(old('deposit_type', $payment?->deposit_type) === 'fixed')>Fixed amount</option>
            <option value="percentage" @selected(old('deposit_type', $payment?->deposit_type) === 'percentage')>Percentage</option>
        </select>
    </label>
    <label>Deposit value<input name="deposit_value" type="number" min="0" step="0.01" value="{{ old('deposit_value', $payment?->deposit_value) }}"></label>
    <label>Balance due days before arrival<input name="balance_due_days_before_arrival" type="number" min="0" value="{{ old('balance_due_days_before_arrival', $payment?->balance_due_days_before_arrival) }}"></label>

    <label class="reserva-commercial-span-2">Inclusions
        <input name="inclusions[]" value="{{ old('inclusions.0', data_get($ratePlan->inclusions, 0)) }}" placeholder="e.g. Breakfast">
        <input name="inclusions[]" value="{{ old('inclusions.1', data_get($ratePlan->inclusions, 1)) }}" placeholder="e.g. Airport pickup">
    </label>
</div>
<div class="reserva-commercial-checks">
    <label><input type="checkbox" name="is_refundable" value="1" @checked(old('is_refundable', $ratePlan->exists ? $ratePlan->is_refundable : true))> Refundable</label>
    <label><input type="checkbox" name="charge_first_night" value="1" @checked(old('charge_first_night', $cancel?->charge_first_night))> Charge first night on cancellation</label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ratePlan->exists ? $ratePlan->is_active : true))> Active</label>
    <label><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $ratePlan->exists ? $ratePlan->is_public : true))> Public</label>
</div>
