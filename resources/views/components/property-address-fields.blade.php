@props(['source' => null, 'wrapperClass' => '', 'labelClass' => ''])
@php
    $value = fn (string $key, mixed $default = null) => old($key, data_get($source, $key, $default));
@endphp

<div class="az-address-fields {{ $wrapperClass }}" data-azari-property-address>
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
        <small>Enter the full address directly. Maps and third-party address services are not required to save a property.</small>
    </label>

    <details class="az-address-optional-fields" style="margin-top:12px">
        <summary>Additional address details (optional)</summary>
        <div class="az-address-manual-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:12px;margin-top:12px">
            <label class="{{ $labelClass }}">Street / building
                <input name="address_line_1" value="{{ $value('address_line_1') }}" maxlength="255" data-azari-address="address_line_1" autocomplete="address-line1">
            </label>
            <label class="{{ $labelClass }}">Apartment / suite
                <input name="address_line_2" value="{{ $value('address_line_2') }}" maxlength="255" data-azari-address="address_line_2" autocomplete="address-line2">
            </label>
            <label class="{{ $labelClass }}">City
                <input name="address_city" value="{{ $value('address_city') }}" maxlength="120" data-azari-address="address_city" autocomplete="address-level2">
            </label>
            <label class="{{ $labelClass }}">State / region
                <input name="address_region" value="{{ $value('address_region') }}" maxlength="120" data-azari-address="address_region" autocomplete="address-level1">
            </label>
            <label class="{{ $labelClass }}">Postal code
                <input name="address_postal_code" value="{{ $value('address_postal_code') }}" maxlength="40" data-azari-address="address_postal_code" autocomplete="postal-code">
            </label>
            <label class="{{ $labelClass }}">Country code (optional, e.g. NG)
                <input name="address_country_code" value="{{ $value('address_country_code') }}" maxlength="2" minlength="2" pattern="[A-Za-z]{2}" data-azari-address="address_country_code" autocomplete="country">
            </label>
        </div>
    </details>

    <details class="az-address-optional-fields" style="margin-top:12px">
        <summary>Map coordinates (optional, can be entered manually)</summary>
        <div class="az-address-manual-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:12px;margin-top:12px">
            <label class="{{ $labelClass }}">Latitude
                <input type="number" name="latitude" step="any" min="-90" max="90"
                       value="{{ $value('latitude') }}" data-azari-address="latitude" inputmode="decimal">
            </label>
            <label class="{{ $labelClass }}">Longitude
                <input type="number" name="longitude" step="any" min="-180" max="180"
                       value="{{ $value('longitude') }}" data-azari-address="longitude" inputmode="decimal">
            </label>
        </div>
        <small>Leave coordinates blank if unknown. A listing can be saved and booked without map coordinates.</small>
    </details>

    <details class="az-address-optional-fields" style="margin-top:12px">
        <summary>Address suggestions (optional)</summary>
        <p><small>Lookups use an external OpenStreetMap-based service only when you search here. You can ignore this section and enter the address yourself.</small></p>
        <label class="{{ $labelClass }}">
            Search for an address
            <input type="search" autocomplete="off" placeholder="Search address suggestions" data-azari-address-search>
        </label>

        <div data-azari-address-status role="status" aria-live="polite" hidden>
            <span class="az-address-spinner" aria-hidden="true" hidden></span>
            <span data-azari-address-status-text></span>
        </div>
        <div data-azari-address-results hidden></div>

        <div style="margin:8px 0 12px">
            <button type="button" data-azari-use-location style="border:1px solid #052058;background:#FFFFFF;border-radius:8px;padding:9px 12px;cursor:pointer">Use current location</button>
        </div>
    </details>
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
        root.dataset.azariSuggestedCoordinates = 'true';
        return values;
    };

    const renderResults = (root, features, onPick = () => {}) => {
        const box = root.querySelector('[data-azari-address-results]');
        if (!box) return;

        box.innerHTML = '';
        if (!Array.isArray(features) || features.length === 0) {
            box.hidden = true;
            return;
        }

        box.hidden = false;
        box.style.border = '1px solid #052058';
        box.style.borderRadius = '8px';
        box.style.overflow = 'hidden';
        box.style.marginBottom = '12px';
        box.style.background = '#FFFFFF';

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
            button.style.borderBottom = '1px solid #052058';
            button.style.background = '#FFFFFF';
            button.style.cursor = 'pointer';
            button.addEventListener('click', () => {
                applyFeature(root, feature);
                box.hidden = true;
                onPick();
            });
            box.appendChild(button);
        });
    };

    document.querySelectorAll('[data-azari-property-address]').forEach((root) => {
        const search = root.querySelector('[data-azari-address-search]');
        const locationButton = root.querySelector('[data-azari-use-location]');
        const box = root.querySelector('[data-azari-address-results]');
        const status = root.querySelector('[data-azari-address-status]');
        const statusText = root.querySelector('[data-azari-address-status-text]');
        const progress = root.querySelector('.az-address-spinner');
        let generation = 0;
        let activeRequest = null;

        const showStatus = (message = '', busy = false) => {
            if (status) status.hidden = !message;
            if (statusText) statusText.textContent = message;
            if (progress) progress.hidden = !busy;
        };

        // A manual address edit must not retain unrelated coordinates from
        // a previous suggested property. Explicitly typed coordinates stay.
        root.addEventListener('input', (event) => {
            const field = event.target?.dataset?.azariAddress;
            if (field === 'latitude' || field === 'longitude') {
                root.dataset.azariSuggestedCoordinates = 'false';
            } else if (field === 'formatted_address' && root.dataset.azariSuggestedCoordinates === 'true') {
                setField(root, 'latitude', '');
                setField(root, 'longitude', '');
                root.dataset.azariSuggestedCoordinates = 'false';
                generation++;
                activeRequest?.abort();
            }
        });

        if (search) {
            search.addEventListener('input', () => {
                // Immediately invalidate stale results when the input changes,
                // rather than waiting for the 450ms debounce to fire.
                generation++;
                activeRequest?.abort();
                activeRequest = null;
                if (box) box.hidden = true;
                showStatus('');
            });
            search.addEventListener('input', debounce(async () => {
                const q = search.value.trim();
                const currentGeneration = generation;
                if (q.length < 3) return;

                const controller = new AbortController();
                activeRequest = controller;
                showStatus('Searching addresses…', true);
                try {
                    const response = await fetch(`${searchEndpoint}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('Address lookup failed');
                    const payload = await response.json();
                    if (currentGeneration !== generation || controller.signal.aborted) return;
                    const features = payload?.features ?? [];
                    renderResults(root, features, () => {
                        generation++;
                        activeRequest?.abort();
                        activeRequest = null;
                        showStatus('');
                    });
                    showStatus(features.length ? '' : 'No matching address found. Enter the address manually.');
                } catch (error) {
                    if (currentGeneration !== generation || controller.signal.aborted) return;
                    if (box) box.hidden = true;
                    showStatus('Address suggestions are unavailable. You can enter the address manually.');
                } finally {
                    if (activeRequest === controller) activeRequest = null;
                }
            }, 450));
        }

        if (locationButton) {
            locationButton.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    showStatus('Location is not supported here. Enter the address manually.');
                    return;
                }

                generation++;
                const locationGeneration = generation;
                activeRequest?.abort();
                if (box) box.hidden = true;
                showStatus('Finding your location…', true);
                locationButton.disabled = true;
                navigator.geolocation.getCurrentPosition(async ({ coords }) => {
                    if (locationGeneration !== generation) {
                        locationButton.disabled = false;
                        return;
                    }
                    setField(root, 'latitude', coords.latitude);
                    setField(root, 'longitude', coords.longitude);
                    root.dataset.azariSuggestedCoordinates = 'true';

                    try {
                        const response = await fetch(`${reverseEndpoint}?lat=${encodeURIComponent(coords.latitude)}&lon=${encodeURIComponent(coords.longitude)}`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (response.ok) {
                            const payload = await response.json();
                            if (locationGeneration !== generation) return;
                            const feature = payload?.features?.[0];
                            if (feature) applyFeature(root, feature);
                            showStatus(feature ? 'Address found. Review it before saving.' : 'Location found. Enter the address manually.');
                        } else {
                            showStatus('Location found. Address lookup unavailable; enter the address manually.');
                        }
                    } catch (error) {
                        showStatus('Location found. Address lookup unavailable; enter the address manually.');
                    } finally {
                        locationButton.disabled = false;
                    }
                }, () => {
                    locationButton.disabled = false;
                    showStatus('Location unavailable or permission denied. Enter the address manually.');
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
