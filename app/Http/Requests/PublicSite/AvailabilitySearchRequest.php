<?php

namespace App\Http\Requests\PublicSite;

use App\Http\Requests\AzariFormRequest;
use Illuminate\Validation\Rule;

class AvailabilitySearchRequest extends AzariFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'adults' => $this->integer('adults', 1),
            'children' => $this->integer('children', 0),
            'location' => trim((string) $this->input('location')),
            'property_type' => trim((string) $this->input('property_type')),
        ]);
    }

    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['required', 'integer', 'min:0', 'max:8'],
            'location' => ['nullable', 'string', 'max:120'],
            'property_type' => [
                'nullable',
                Rule::in(['', 'apartment', 'room', 'studio', 'penthouse']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.required' => 'Choose a check-in date.',
            'check_in.after_or_equal' => 'Check-in cannot be in the past.',
            'check_out.required' => 'Choose a check-out date.',
            'check_out.after' => 'Check-out must be after check-in.',
            'adults.min' => 'At least one adult is required.',
        ];
    }
}
