@props(['source' => null, 'wrapperClass' => '', 'labelClass' => ''])
@php
    $value = fn (string $key, mixed $default = null) => old($key, data_get($source, $key, $default));
@endphp

<div class="az-address-fields {{ $wrapperClass }}" data-azari-property-address>
    <label class="{{ $labelClass }}">
        Search property address
        <input type="search" autocomplete="off" placeholder="Start typing an address" data-azari-address-search>
        <small>Address suggestions use OpenStreetMap data. No Google API key is required, and manual entry remains available.</small>
    </label>

    <div data-azari-address-results hidden></div>

    <div style="margin:8px 0 12px">
        <button type="button" data-azari-use-location style="border:1px solid #d7d7d7;background:#fff;border-radius:8px;padding:9px 12px;cursor:pointer">Use current location</button>
    </div>

    <label class="{{ $labelClass }}">
        Property address
        <input
            name="formatted_address"
            value="{{ $value('formatted_address') }}"
            maxlength="1000"
            required
            autocomplete="street-address"
            data-azari-address="formatted_address"
        >
        <small>You can type or correct this address manually at any time.</small>
    </label>

    <div style="display:none" aria-hidden="true">
        <input name="address_line_1" value="{{ $value('address_line_1') }}" data-azari-address="address_line_1">
        <input name="address_line_2" value="{{ $value('address_line_2') }}" data-azari-address="address_line_2">
        <input name="address_city" value="{{ $value('address_city') }}" data-azari-address="address_city">
        <input name="address_region" value="{{ $value('address_region') }}" data-azari-address="address_region">
        <input name="address_postal_code" value="{{ $value('address_postal_code') }}" data-azari-address="address_postal_code">
        <input name="address_country_code" value="{{ $value('address_country_code') }}" data-azari-address="address_country_code">
        <input name="latitude" value="{{ $value('latitude') }}" data-azari-address="latitude">
        <input name="longitude" value="{{ $value('longitude') }}" data-azari-address="longitude">
    </div>
</div>

@once
<script>
(() => {
    const searchEndpoint = @json(route('location.address.search'));
    const reverseEndpoint = @json(route('location.address.reverse'));

    const debounce = (fn, delay = 450) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    const setField = (root, name, value) => {
        const input = root.querySelector(`[data-azari-address="${name}"]`);
        if (input) input.value = value ?? '';
    };

    const uniqueParts = (parts) => parts.filter((part, index, all) => part && all.indexOf(part) === index);

    const normaliseFeature = (feature) => {
        const p = feature?.properties ?? {};
        const coords = feature?.geometry?.coordinates ?? [];
        const line1 = [p.housenumber, p.street].filter(Boolean).join(' ').trim();
        const primary = line1 || p.name || '';
        const city = p.city || p.town || p.village || p.district || p.county || '';
        const region = p.state || p.county || '';
        const country = p.country || '';
        const postcode = p.postcode || '';
        const formatted = uniqueParts([primary, city, region, postcode, country]).join(', ');

        return {
            formatted_address: formatted || p.name || '',
            address_line_1: primary,
            address_line_2: '',
            address_city: city,
            address_region: region,
            address_postal_code: postcode,
            address_country_code: (p.countrycode || '').toUpperCase(),
            latitude: coords[1] ?? '',
            longitude: coords[0] ?? '',
        };
    };

    const applyFeature = (root, feature) => {
        const values = normaliseFeature(feature);
        Object.entries(values).forEach(([key, value]) => setField(root, key, value));
        return values;
    };

    const renderResults = (root, features) => {
        const box = root.querySelector('[data-azari-address-results]');
        if (!box) return;

        box.innerHTML = '';
        if (!Array.isArray(features) || features.length === 0) {
            box.hidden = true;
            return;
        }

        box.hidden = false;
        box.style.border = '1px solid #ddd';
        box.style.borderRadius = '8px';
        box.style.overflow = 'hidden';
        box.style.marginBottom = '12px';
        box.style.background = '#fff';

        features.slice(0, 5).forEach((feature) => {
            const values = normaliseFeature(feature);
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = values.formatted_address;
            button.style.display = 'block';
            button.style.width = '100%';
            button.style.padding = '10px 12px';
            button.style.textAlign = 'left';
            button.style.border = '0';
            button.style.borderBottom = '1px solid #eee';
            button.style.background = '#fff';
            button.style.cursor = 'pointer';
            button.addEventListener('click', () => {
                applyFeature(root, feature);
                box.hidden = true;
            });
            box.appendChild(button);
        });
    };

    document.querySelectorAll('[data-azari-property-address]').forEach((root) => {
        const search = root.querySelector('[data-azari-address-search]');
        const locationButton = root.querySelector('[data-azari-use-location]');
        const box = root.querySelector('[data-azari-address-results]');

        if (search) {
            search.addEventListener('input', debounce(async () => {
                const q = search.value.trim();
                if (q.length < 3) {
                    if (box) box.hidden = true;
                    return;
                }

                try {
                    const response = await fetch(`${searchEndpoint}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error(`Address lookup ${response.status}`);
                    const payload = await response.json();
                    renderResults(root, payload?.features ?? []);
                } catch (error) {
                    if (box) box.hidden = true;
                    console.warn('Address suggestions are temporarily unavailable. Manual entry remains available.', error);
                }
            }, 450));
        }

        if (locationButton) {
            locationButton.addEventListener('click', () => {
                if (!navigator.geolocation) return;

                locationButton.disabled = true;
                navigator.geolocation.getCurrentPosition(async ({ coords }) => {
                    setField(root, 'latitude', coords.latitude);
                    setField(root, 'longitude', coords.longitude);

                    try {
                        const response = await fetch(`${reverseEndpoint}?lat=${encodeURIComponent(coords.latitude)}&lon=${encodeURIComponent(coords.longitude)}`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (response.ok) {
                            const payload = await response.json();
                            const feature = payload?.features?.[0];
                            if (feature) applyFeature(root, feature);
                        }
                    } catch (error) {
                        console.warn('Reverse geocoding is temporarily unavailable.', error);
                    } finally {
                        locationButton.disabled = false;
                    }
                }, () => {
                    locationButton.disabled = false;
                }, {
                    enableHighAccuracy: false,
                    timeout: 10000,
                    maximumAge: 300000,
                });
            });
        }
    });
})();
</script>
@endonce
