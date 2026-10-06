@php
    $coreSearch = collect($filters)->only([
        'check_in', 'check_out', 'adults', 'children', 'rooms',
        'destination', 'destination_type', 'destination_id',
        'location_id', 'room_type_id', 'property_id',
    ])->filter(fn ($value) => $value !== null && $value !== '');
@endphp

@foreach($coreSearch as $name => $value)
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
@endforeach

<fieldset>
    <legend>Price per night</legend>
    <label>
        <span class="sr-only">Minimum nightly price</span>
        <input type="number" name="price_min" min="0" step="1" placeholder="Minimum" value="{{ $filters['price_min'] ?? '' }}">
    </label>
    <label>
        <span class="sr-only">Maximum nightly price</span>
        <input type="number" name="price_max" min="0" step="1" placeholder="Maximum" value="{{ $filters['price_max'] ?? '' }}">
    </label>
</fieldset>

<fieldset>
    <legend>Property</legend>
    <label>
        <span>Type</span>
        <select name="property_type">
            <option value="">Any type</option>
            @foreach(($facets['property_types'] ?? []) as $type => $count)
                <option value="{{ $type }}" @selected(($filters['property_type'] ?? '') === $type)>
                    {{ Str::headline($type) }} ({{ $count }})
                </option>
            @endforeach
        </select>
    </label>
    <label>
        <span>Bedrooms</span>
        <input type="number" name="bedrooms" min="0" max="20" value="{{ $filters['bedrooms'] ?? '' }}" placeholder="Any">
    </label>
    <label>
        <span>Bathrooms</span>
        <input type="number" name="bathrooms" min="0" max="20" value="{{ $filters['bathrooms'] ?? '' }}" placeholder="Any">
    </label>
</fieldset>

<fieldset>
    <legend>Guest rating</legend>
    <select name="guest_rating">
        <option value="">Any rating</option>
        @foreach([4.5 => '4.5+ Exceptional', 4 => '4.0+ Very good', 3 => '3.0+ Good'] as $value => $label)
            <option value="{{ $value }}" @selected((string) ($filters['guest_rating'] ?? '') === (string) $value)>{{ $label }}</option>
        @endforeach
    </select>
</fieldset>

<fieldset>
    <legend>Booking terms</legend>
    <label><input type="checkbox" name="free_cancellation" value="1" @checked(!empty($filters['free_cancellation']))> Free cancellation</label>
    <label><input type="checkbox" name="pay_later" value="1" @checked(!empty($filters['pay_later']))> Pay later / deposit</label>
    <label><input type="checkbox" name="breakfast" value="1" @checked(!empty($filters['breakfast']))> Breakfast</label>
</fieldset>

<fieldset>
    <legend>Popular facilities</legend>
    @foreach([
        'wifi' => 'Wi-Fi',
        'parking' => 'Parking',
        'pool' => 'Pool',
        'kitchen' => 'Kitchen',
        'air_conditioning' => 'Air conditioning',
        'accessibility' => 'Accessibility',
    ] as $key => $label)
        <label><input type="checkbox" name="{{ $key }}" value="1" @checked(!empty($filters[$key]))> {{ $label }}</label>
    @endforeach
</fieldset>

@if(($facets['amenities'] ?? []) !== [])
    <fieldset>
        <legend>More amenities</legend>
        @foreach(array_slice($facets['amenities'], 0, 12) as $amenity)
            <label>
                <input type="checkbox" name="amenities[]" value="{{ $amenity['id'] }}" @checked(in_array($amenity['id'], $filters['amenities'] ?? [], true))>
                {{ $amenity['name'] }} ({{ $amenity['count'] }})
            </label>
        @endforeach
    </fieldset>
@endif

<fieldset>
    <legend>Neighbourhood</legend>
    <input type="search" name="neighbourhood" maxlength="120" placeholder="Area or neighbourhood" value="{{ $filters['neighbourhood'] ?? '' }}">
</fieldset>


<div class="reserva-filter-actions">
    <button class="button button-primary" type="submit">Apply filters</button>
    <a class="button button-secondary" href="{{ route('availability.results', $coreSearch->all()) }}">Clear</a>
</div>
