<?php

namespace App\Http\Requests\CommercialInventory;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'same_day_booking' => $this->boolean('same_day_booking'),
            'is_active' => $this->boolean('is_active'),
            'is_published' => $this->boolean('is_published'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'adult_capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'child_capacity' => ['required', 'integer', 'min:0', 'max:100'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:100'],
            'bed_configuration' => ['nullable', 'string', 'max:255'],
            'room_size' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'total_inventory' => ['required', 'integer', 'min:1', 'max:10000'],
            'base_rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'weekend_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'security_deposit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'same_day_booking' => ['boolean'],
            'is_active' => ['boolean'],
            'is_published' => ['boolean'],
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

                $adult = max(0, $this->integer('adult_capacity'));
                $child = max(0, $this->integer('child_capacity'));
                $maximumGuests = max(0, $this->integer('max_guests'));

                if ($maximumGuests > ($adult + $child)) {
                    $validator->errors()->add(
                        'max_guests',
                        'Maximum guests cannot exceed the combined adult and child capacity.'
                    );
                }
            },
        ];
    }
}
