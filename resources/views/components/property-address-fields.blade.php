@props(['source' => null, 'wrapperClass' => '', 'labelClass' => ''])
@php
    $value = fn (string $key, mixed $default = null) => old($key, data_get($source, $key, $default));
    $mapsEnabled = (bool) config('azari.maps.enabled', false)
        && config('azari.maps.provider', 'google') === 'google'
        && filled(config('azari.maps.api_key'));
@endphp

<div class="az-address-fields {{ $wrapperClass }}" data-azari-property-address>
    @if($mapsEnabled)
        <label class="{{ $labelClass }}">
            Search property address
            <span data-azari-place-autocomplete></span>
            <small>Select the exact Google result. The formatted address, Place ID and coordinates are saved automatically.</small>
        </label>
    @endif

    <label class="{{ $labelClass }}">
        Property address
        <input
            name="formatted_address"
            value="{{ $value('formatted_address') }}"
            maxlength="1000"
            required
            @readonly($mapsEnabled)
            autocomplete="street-address"
            data-azari-address="formatted_address"
        >
    </label>

    <div style="display:none" aria-hidden="true">
        <input name="address_line_1" value="{{ $value('address_line_1') }}" data-azari-address="address_line_1">
        <input name="address_line_2" value="{{ $value('address_line_2') }}" data-azari-address="address_line_2">
        <input name="address_city" value="{{ $value('address_city') }}" data-azari-address="address_city">
        <input name="address_region" value="{{ $value('address_region') }}" data-azari-address="address_region">
        <input name="address_postal_code" value="{{ $value('address_postal_code') }}" data-azari-address="address_postal_code">
        <input name="address_country_code" value="{{ $value('address_country_code') }}" data-azari-address="address_country_code">
        <input name="google_place_id" value="{{ $value('google_place_id') }}" data-azari-address="google_place_id">
        <input name="latitude" value="{{ $value('latitude') }}" data-azari-address="latitude">
        <input name="longitude" value="{{ $value('longitude') }}" data-azari-address="longitude">
    </div>
</div>

@if($mapsEnabled)
@once
<script>
window.azariInitPropertyAddress = async function () {
    const roots = document.querySelectorAll('[data-azari-property-address]');
    if (!roots.length || !window.google?.maps) return;

    const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');

    const text = (component) => component?.longText ?? component?.long_name ?? '';
    const shortText = (component) => component?.shortText ?? component?.short_name ?? text(component);

    roots.forEach((root) => {
        const mount = root.querySelector('[data-azari-place-autocomplete]');
        if (!mount || mount.dataset.ready === '1') return;

        const autocomplete = new PlaceAutocompleteElement();
        autocomplete.setAttribute('aria-label', 'Search property address');
        autocomplete.style.width = '100%';
        mount.appendChild(autocomplete);
        mount.dataset.ready = '1';

        autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
            try {
                const place = placePrediction.toPlace();
                await place.fetchFields({
                    fields: ['id', 'formattedAddress', 'location', 'addressComponents'],
                });

                const components = Array.isArray(place.addressComponents) ? place.addressComponents : [];
                const first = (type) => components.find((component) => Array.isArray(component.types) && component.types.includes(type));
                const streetNumber = text(first('street_number'));
                const route = text(first('route'));
                const lineOne = [streetNumber, route].filter(Boolean).join(' ').trim();
                const subpremise = text(first('subpremise'));
                const city = text(first('locality'))
                    || text(first('postal_town'))
                    || text(first('sublocality_level_1'))
                    || text(first('administrative_area_level_2'));
                const region = text(first('administrative_area_level_1'));
                const postalCode = text(first('postal_code'));
                const countryCode = shortText(first('country')).toUpperCase();

                const values = {
                    formatted_address: place.formattedAddress ?? '',
                    address_line_1: lineOne,
                    address_line_2: subpremise,
                    address_city: city,
                    address_region: region,
                    address_postal_code: postalCode,
                    address_country_code: countryCode,
                    google_place_id: place.id ?? '',
                    latitude: typeof place.location?.lat === 'function' ? place.location.lat() : (place.location?.lat ?? ''),
                    longitude: typeof place.location?.lng === 'function' ? place.location.lng() : (place.location?.lng ?? ''),
                };

                Object.entries(values).forEach(([key, value]) => {
                    const input = root.querySelector(`[data-azari-address="${key}"]`);
                    if (input) input.value = value ?? '';
                });
            } catch (error) {
                console.error('Azari property address selection failed.', error);
            }
        });
    });
};
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode((string) config('azari.maps.api_key')) }}&libraries=places&loading=async&callback=azariInitPropertyAddress"></script>
@endonce
@endif
