<?php

namespace App\Http\Requests\PublicSite;

use App\Http\Requests\AzariFormRequest;
use Illuminate\Validation\Rule;

class MarketplaceSearchRequest extends AzariFormRequest
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
            'rooms' => $this->integer('rooms', 1),
            'destination' => trim((string) $this->input('destination')),
            'amenities' => array_values(array_filter(array_map('intval', (array) $this->input('amenities', [])))),
        ]);
    }

    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'destination' => ['nullable', 'string', 'max:120'],
            'destination_type' => ['nullable', Rule::in(['location', 'property', 'city', 'country'])],
            'destination_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'property_type' => ['nullable', 'string', 'max:80'],
            'accommodation_type_id' => ['nullable', 'integer', 'exists:accommodation_types,id'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'price_max' => ['nullable', 'numeric', 'gte:price_min', 'max:100000000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'guest_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'amenities' => ['nullable', 'array', 'max:20'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'wifi' => ['nullable', 'boolean'],
            'parking' => ['nullable', 'boolean'],
            'pool' => ['nullable', 'boolean'],
            'kitchen' => ['nullable', 'boolean'],
            'breakfast' => ['nullable', 'boolean'],
            'air_conditioning' => ['nullable', 'boolean'],
            'accessibility' => ['nullable', 'boolean'],
            'free_cancellation' => ['nullable', 'boolean'],
            'pay_later' => ['nullable', 'boolean'],
            'neighbourhood' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc', 'rating', 'distance', 'popularity'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
