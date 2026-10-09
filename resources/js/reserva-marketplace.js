const mapRoot = document.querySelector('[data-reserva-results-map]');
const listPane = document.querySelector('[data-results-pane="list"]');
const mapPane = document.querySelector('[data-results-pane="map"]');

document.querySelectorAll('[data-results-view]').forEach((button) => {
    button.addEventListener('click', () => {
        const view = button.dataset.resultsView;

        document.querySelectorAll('[data-results-view]').forEach((item) => {
            const active = item.dataset.resultsView === view;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (listPane) {
            listPane.hidden = view !== 'list';
        }

        if (mapPane) {
            mapPane.hidden = view !== 'map';
        }

        if (view === 'map') {
            mapRoot?.querySelector('button')?.focus({ preventScroll: true });
        }
    });
});

document.querySelectorAll('[data-search-sort]').forEach((select) => {
    select.addEventListener('change', () => select.form?.requestSubmit());
});

if (mapRoot) {
    const source = document.querySelector('[data-map-points]');
    let points = [];

    try {
        points = JSON.parse(source?.textContent || '[]')
            .map((point) => ({ ...point, lat: Number(point.lat), lng: Number(point.lng) }))
            .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
    } catch {
        points = [];
    }

    const normalizeLng = (value) => {
        let lng = Number(value);
        while (lng > 180) lng -= 360;
        while (lng < -180) lng += 360;
        return lng;
    };

    const unwrapPoints = (items) => {
        if (!items.length) return [];
        const raw = items.map((point) => normalizeLng(point.lng));
        const ordinarySpan = Math.max(...raw) - Math.min(...raw);

        if (ordinarySpan <= 180) {
            return items.map((point) => ({ ...point, mapLng: normalizeLng(point.lng) }));
        }

        return items.map((point) => {
            const lng = normalizeLng(point.lng);
            return { ...point, mapLng: lng < 0 ? lng + 360 : lng };
        });
    };

    const mapped = unwrapPoints(points);

    if (mapped.length) {
        const lats = mapped.map((point) => point.lat);
        const lngs = mapped.map((point) => point.mapLng);
        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);
        const baseLatSpan = Math.max(0.02, maxLat - minLat);
        const baseLngSpan = Math.max(0.02, maxLng - minLng);

        const state = {
            centerLat: (minLat + maxLat) / 2,
            centerLng: (minLng + maxLng) / 2,
            latSpan: Math.min(180, baseLatSpan * 1.35),
            lngSpan: Math.min(360, baseLngSpan * 1.35),
        };
        const initial = { ...state };

        mapRoot.tabIndex = 0;
        mapRoot.setAttribute('aria-label', 'Interactive property map. Use arrow keys to pan and plus or minus to zoom.');

        const controls = document.createElement('div');
        controls.className = 'reserva-map-controls';
        controls.innerHTML =
            '<button type="button" data-map-zoom-in aria-label="Zoom in">+</button>' +
            '<button type="button" data-map-zoom-out aria-label="Zoom out">−</button>' +
            '<button type="button" data-map-reset>Reset</button>' +
            '<button type="button" class="reserva-map-search-area" data-map-search-area>Search this area</button>';

        const status = document.createElement('div');
        status.className = 'reserva-map-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');

        const pinLayer = document.createElement('div');
        pinLayer.className = 'reserva-map-pin-layer';

        mapRoot.replaceChildren(pinLayer, controls, status);

        const bounds = () => ({
            north: Math.min(90, state.centerLat + state.latSpan / 2),
            south: Math.max(-90, state.centerLat - state.latSpan / 2),
            westRaw: state.centerLng - state.lngSpan / 2,
            eastRaw: state.centerLng + state.lngSpan / 2,
        });

        const project = (point) => {
            const box = bounds();
            const x = ((point.mapLng - box.westRaw) / Math.max(0.000001, state.lngSpan)) * 100;
            const y = ((box.north - point.lat) / Math.max(0.000001, state.latSpan)) * 100;
            return { x, y };
        };

        const zoom = (factor, focusLat = state.centerLat, focusLng = state.centerLng) => {
            const nextLatSpan = Math.min(180, Math.max(0.002, state.latSpan * factor));
            const nextLngSpan = Math.min(360, Math.max(0.002, state.lngSpan * factor));

            state.centerLat = Math.max(-90, Math.min(90, focusLat));
            state.centerLng = focusLng;
            state.latSpan = nextLatSpan;
            state.lngSpan = nextLngSpan;
            render();
        };

        const activateProperty = (point, pin) => {
            const card = document.querySelector('[data-property-card="' + point.id + '"]');
            document.querySelectorAll('[data-property-card]').forEach((item) => item.classList.remove('is-map-active'));
            document.querySelectorAll('.reserva-map-pin').forEach((item) => item.classList.remove('is-active'));

            pin.classList.add('is-active');
            card?.classList.add('is-map-active');

            if (card) {
                document.querySelector('[data-results-view="list"]')?.click();
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.querySelector('a,button')?.focus({ preventScroll: true });
            }
        };

        const render = () => {
            pinLayer.replaceChildren();
            const width = Math.max(320, mapRoot.clientWidth || 800);
            const height = Math.max(320, mapRoot.clientHeight || 620);
            const clusters = new Map();

            mapped.forEach((point) => {
                const projected = project(point);
                if (projected.x < 0 || projected.x > 100 || projected.y < 0 || projected.y > 100) return;

                const xPx = (projected.x / 100) * width;
                const yPx = (projected.y / 100) * height;
                const key = Math.floor(xPx / 82) + ':' + Math.floor(yPx / 64);

                if (!clusters.has(key)) clusters.set(key, []);
                clusters.get(key).push({ point, projected });
            });

            clusters.forEach((cluster) => {
                if (cluster.length > 1) {
                    const avgX = cluster.reduce((sum, item) => sum + item.projected.x, 0) / cluster.length;
                    const avgY = cluster.reduce((sum, item) => sum + item.projected.y, 0) / cluster.length;
                    const avgLat = cluster.reduce((sum, item) => sum + item.point.lat, 0) / cluster.length;
                    const avgLng = cluster.reduce((sum, item) => sum + item.point.mapLng, 0) / cluster.length;

                    const pin = document.createElement('button');
                    pin.type = 'button';
                    pin.className = 'reserva-map-pin reserva-map-cluster';
                    pin.style.left = avgX + '%';
                    pin.style.top = avgY + '%';
                    pin.textContent = String(cluster.length);
                    pin.setAttribute('aria-label', cluster.length + ' properties in this area. Zoom in.');
                    pin.addEventListener('click', () => zoom(0.45, avgLat, avgLng));
                    pinLayer.append(pin);
                    return;
                }

                const item = cluster[0];
                const point = item.point;
                const pin = document.createElement('button');
                pin.type = 'button';
                pin.className = 'reserva-map-pin';
                pin.dataset.propertyId = String(point.id);
                pin.style.left = item.projected.x + '%';
                pin.style.top = item.projected.y + '%';
                pin.textContent = point.currency + ' ' + Number(point.price).toLocaleString(undefined, { maximumFractionDigits: 0 });
                pin.setAttribute('aria-label', point.name + ', ' + point.currency + ' ' + point.price);
                pin.addEventListener('click', () => activateProperty(point, pin));
                pinLayer.append(pin);
            });

            const visible = Array.from(clusters.values()).reduce((sum, cluster) => sum + cluster.length, 0);
            status.textContent = visible + ' mapped ' + (visible === 1 ? 'property' : 'properties') + ' in the current viewport.';
        };

        controls.querySelector('[data-map-zoom-in]').addEventListener('click', () => zoom(0.55));
        controls.querySelector('[data-map-zoom-out]').addEventListener('click', () => zoom(1.8));
        controls.querySelector('[data-map-reset]').addEventListener('click', () => {
            Object.assign(state, initial);
            render();
        });

        controls.querySelector('[data-map-search-area]').addEventListener('click', () => {
            const box = bounds();
            const url = new URL(window.location.href);
            url.searchParams.set('north', box.north.toFixed(6));
            url.searchParams.set('south', box.south.toFixed(6));
            url.searchParams.set('west', normalizeLng(box.westRaw).toFixed(6));
            url.searchParams.set('east', normalizeLng(box.eastRaw).toFixed(6));
            url.searchParams.delete('page');
            window.location.assign(url.toString());
        });

        mapRoot.addEventListener('keydown', (event) => {
            const stepLat = state.latSpan * 0.15;
            const stepLng = state.lngSpan * 0.15;
            let handled = true;

            if (event.key === 'ArrowUp') state.centerLat = Math.min(90, state.centerLat + stepLat);
            else if (event.key === 'ArrowDown') state.centerLat = Math.max(-90, state.centerLat - stepLat);
            else if (event.key === 'ArrowLeft') state.centerLng -= stepLng;
            else if (event.key === 'ArrowRight') state.centerLng += stepLng;
            else if (event.key === '+' || event.key === '=') zoom(0.65);
            else if (event.key === '-') zoom(1.5);
            else handled = false;

            if (handled) {
                event.preventDefault();
                if (!['+', '=', '-'].includes(event.key)) render();
            }
        });

        let drag = null;
        mapRoot.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button')) return;
            drag = { x: event.clientX, y: event.clientY, lat: state.centerLat, lng: state.centerLng };
            mapRoot.setPointerCapture?.(event.pointerId);
            mapRoot.classList.add('is-dragging');
        });

        mapRoot.addEventListener('pointermove', (event) => {
            if (!drag) return;
            const width = Math.max(1, mapRoot.clientWidth);
            const height = Math.max(1, mapRoot.clientHeight);
            state.centerLng = drag.lng - ((event.clientX - drag.x) / width) * state.lngSpan;
            state.centerLat = Math.max(-90, Math.min(90, drag.lat + ((event.clientY - drag.y) / height) * state.latSpan));
            render();
        });

        const endDrag = () => {
            drag = null;
            mapRoot.classList.remove('is-dragging');
        };
        mapRoot.addEventListener('pointerup', endDrag);
        mapRoot.addEventListener('pointercancel', endDrag);

        mapRoot.addEventListener('wheel', (event) => {
            event.preventDefault();
            zoom(event.deltaY < 0 ? 0.8 : 1.25);
        }, { passive: false });

        window.addEventListener('resize', render, { passive: true });
        render();
    } else {
        mapRoot.classList.add('is-empty');
        mapRoot.textContent = 'Map coordinates are not available for these results. They remain available in the list.';
    }
}

document.querySelectorAll('[data-property-card]').forEach((card) => {
    const setActive = (active) => {
        const id = card.dataset.propertyCard;
        const pin = document.querySelector(`.reserva-map-pin[data-property-id="${id}"]`);
        pin?.classList.toggle('is-active', active);
    };

    card.addEventListener('mouseenter', () => setActive(true));
    card.addEventListener('mouseleave', () => setActive(false));
    card.addEventListener('focusin', () => setActive(true));
    card.addEventListener('focusout', () => setActive(false));
});
