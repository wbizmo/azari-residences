<?php

namespace App\Http\Requests\CommercialInventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_refundable' => $this->boolean('is_refundable'),
            'is_active' => $this->boolean('is_active'),
            'is_public' => $this->boolean('is_public'),
            'charge_first_night' => $this->boolean('charge_first_night'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'pricing_adjustment_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'pricing_adjustment' => ['nullable', 'numeric', 'between:-1000000000,1000000000'],
            'meal_plan' => ['nullable', 'string', 'max:120'],
            'inclusions' => ['nullable', 'array', 'max:20'],
            'inclusions.*' => ['nullable', 'string', 'max:160'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'minimum_advance_days' => ['nullable', 'integer', 'min:0', 'max:1095'],
            'maximum_advance_days' => ['nullable', 'integer', 'min:0', 'max:1095'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cancellation_name' => ['nullable', 'string', 'max:180'],
            'cancellation_type' => ['nullable', Rule::in(['property_default', 'flexible', 'non_refundable'])],
            'free_cancel_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'cancellation_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cancellation_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'no_show_policy' => ['nullable', Rule::in(['same_as_cancellation', 'full_stay', 'first_night'])],
            'payment_name' => ['nullable', 'string', 'max:180'],
            'payment_type' => ['required', Rule::in(['full_prepayment', 'deposit', 'pay_later', 'pay_at_property'])],
            'deposit_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'deposit_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'balance_due_days_before_arrival' => ['nullable', 'integer', 'min:0', 'max:1095'],
            'is_refundable' => ['boolean'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'charge_first_night' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $minimum = $this->integer('minimum_stay');
                $maximum = $this->integer('maximum_stay');

                if ($minimum > 0 && $maximum > 0 && $maximum < $minimum) {
                    $validator->errors()->add(
                        'maximum_stay',
                        'Maximum stay cannot be less than minimum stay.'
                    );
                }

                $minimumAdvance = $this->input('minimum_advance_days');
                $maximumAdvance = $this->input('maximum_advance_days');

                if (
                    $minimumAdvance !== null
                    && $maximumAdvance !== null
                    && (int) $maximumAdvance < (int) $minimumAdvance
                ) {
                    $validator->errors()->add(
                        'maximum_advance_days',
                        'Maximum advance days cannot be less than minimum advance days.'
                    );
                }

                if ($this->input('payment_type') === 'deposit') {
                    if (! $this->filled('deposit_type')) {
                        $validator->errors()->add(
                            'deposit_type',
                            'Choose how the deposit is calculated.'
                        );
                    }

                    if (! $this->filled('deposit_value')) {
                        $validator->errors()->add(
                            'deposit_value',
                            'Enter the deposit amount or percentage.'
                        );
                    }
                }

                if (
                    $this->input('deposit_type') === 'percentage'
                    && (float) $this->input('deposit_value', 0) > 100
                ) {
                    $validator->errors()->add(
                        'deposit_value',
                        'Percentage deposits cannot exceed 100%.'
                    );
                }
            },
        ];
    }
}
