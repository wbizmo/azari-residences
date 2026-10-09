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
    try { points = JSON.parse(source?.textContent || '[]'); } catch { points = []; }

    // Mercator tiles represent real geographical positions. Only request visible
    // tiles; do not attempt background tile downloads or user geolocation.
    const valid = points.filter(point => Number.isFinite(Number(point.lat)) &&
        Number.isFinite(Number(point.lng)) && Math.abs(Number(point.lat)) <= 85.05112878 &&
        Math.abs(Number(point.lng)) <= 180);
    const clamp = (number, min, max) => Math.min(max, Math.max(min, number));
    const toWorld = (lat, lng, zoom) => {
        const latitude = clamp(lat, -85.05112878, 85.05112878) * Math.PI / 180;
        const scale = 256 * 2 ** zoom;
        return {
            x: ((lng + 180) / 360) * scale,
            y: (1 - Math.log(Math.tan(latitude) + 1 / Math.cos(latitude)) / Math.PI) * scale / 2
        };
    };
    const fromWorld = (x, y, zoom) => {
        const scale = 256 * 2 ** zoom;
        const lat = Math.atan(Math.sinh(Math.PI * (1 - 2 * y / scale))) * 180 / Math.PI;
        const lng = ((x / scale * 360 + 180) % 360 + 360) % 360 - 180;
        return {lat, lng};
    };

    if (valid.length) {
        mapRoot.replaceChildren();
        mapRoot.classList.add('reserva-map-shell--geographic');
        const canvas = document.createElement('div');
        canvas.className = 'reserva-map-canvas';
        const tiles = document.createElement('div');
        tiles.className = 'reserva-map-tiles';
        const markers = document.createElement('div');
        markers.className = 'reserva-map-markers';
        canvas.append(tiles, markers);
        mapRoot.append(canvas);

        const tools = document.createElement('div');
        tools.className = 'reserva-map-controls';
        const zoomIn = document.createElement('button');
        const zoomOut = document.createElement('button');
        zoomIn.type = zoomOut.type = 'button';
        zoomIn.textContent = '+';
        zoomOut.textContent = '−';
        zoomIn.setAttribute('aria-label', 'Zoom map in');
        zoomOut.setAttribute('aria-label', 'Zoom map out');
        const searchArea = document.createElement('button');
        searchArea.type = 'button';
        searchArea.textContent = 'Search this area';
        searchArea.className = 'reserva-map-search-area';
        tools.append(zoomIn, zoomOut, searchArea);
        mapRoot.append(tools);

        const attribution = document.createElement('a');
        attribution.className = 'reserva-map-attribution';
        attribution.href = 'https://www.openstreetmap.org/copyright';
        attribution.target = '_blank';
        attribution.rel = 'noopener noreferrer';
        attribution.textContent = '© OpenStreetMap contributors';
        mapRoot.append(attribution);

        // Use the actual geographic midpoint of the current result page.
        // Antimeridian crossings are treated as a short arc, not 360 degrees.
        const meanLat = valid.reduce((sum, p) => sum + Number(p.lat), 0) / valid.length;
        const angles = valid.map(p => Number(p.lng) * Math.PI / 180);
        const meanLng = Math.atan2(
            angles.reduce((sum, angle) => sum + Math.sin(angle), 0),
            angles.reduce((sum, angle) => sum + Math.cos(angle), 0)
        ) * 180 / Math.PI;
        let zoom = 11;
        let center = {lat: meanLat, lng: meanLng};
        let isActive = false;

        const resize = () => ({width: Math.max(1, canvas.clientWidth), height: Math.max(1, canvas.clientHeight)});
        const render = () => {
            if (!isActive) return;
            const {width, height} = resize();
            const location = toWorld(center.lat, center.lng, zoom);
            const scale = 256 * 2 ** zoom;
            const left = location.x - width / 2;
            const top = location.y - height / 2;
            const minX = Math.floor(left / 256);
            const maxX = Math.ceil((left + width) / 256);
            const minY = Math.max(0, Math.floor(top / 256));
            const maxY = Math.min(2 ** zoom - 1, Math.ceil((top + height) / 256));

            // Replace small fixed visible tile set on pan/zoom. No prefetch.
            const fragment = document.createDocumentFragment();
            for (let x = minX; x <= maxX && fragment.childNodes.length < 60; x++) {
                for (let y = minY; y <= maxY && fragment.childNodes.length < 60; y++) {
                    const tile = document.createElement('img');
                    const wrappedX = ((x % (2 ** zoom)) + 2 ** zoom) % (2 ** zoom);
                    tile.src = 'https://tile.openstreetmap.org/' + zoom + '/' + wrappedX + '/' + y + '.png';
                    tile.alt = '';
                    tile.loading = 'lazy';
                    tile.decoding = 'async';
                    tile.draggable = false;
                    tile.style.left = x * 256 - left + 'px';
                    tile.style.top = y * 256 - top + 'px';
                    fragment.append(tile);
                }
            }
            tiles.replaceChildren(fragment);

            markers.replaceChildren();
            valid.forEach(point => {
                const coords = toWorld(Number(point.lat), Number(point.lng), zoom);
                // Wrap longitudes so nearby markers do not jump across the map.
                let deltaX = coords.x - location.x;
                if (deltaX > scale / 2) deltaX -= scale;
                if (deltaX < -scale / 2) deltaX += scale;
                const px = deltaX + width / 2;
                const py = coords.y - location.y + height / 2;
                if (px < -60 || py < -30 || px > width + 60 || py > height + 30) return;
                const pin = document.createElement('button');
                pin.type = 'button';
                pin.className = 'reserva-map-pin';
                pin.dataset.propertyId = String(point.id);
                pin.style.left = px + 'px';
                pin.style.top = py + 'px';
                pin.textContent = point.currency + ' ' + Number(point.price).toLocaleString(undefined, {maximumFractionDigits: 0});
                pin.setAttribute('aria-label', point.name + ', ' + point.currency + ' ' + point.price);
                pin.addEventListener('click', () => {
                    const card = document.querySelector('[data-property-card="' + CSS.escape(String(point.id)) + '"]');
                    if (!card) return;
                    document.querySelector('[data-results-view="list"]')?.click();
                    card.classList.add('is-map-active');
                    card.scrollIntoView({behavior: 'smooth', block: 'center'});
                    card.querySelector('a,button')?.focus({preventScroll: true});
                });
                markers.append(pin);
            });
        };
        const move = (deltaX, deltaY) => {
            const world = toWorld(center.lat, center.lng, zoom);
            center = fromWorld(world.x + deltaX, clamp(world.y + deltaY, 0, 256 * 2 ** zoom), zoom);
            render();
        };
        zoomIn.addEventListener('click', () => { zoom = Math.min(16, zoom + 1); render(); });
        zoomOut.addEventListener('click', () => { zoom = Math.max(3, zoom - 1); render(); });
        let drag = null;
        canvas.addEventListener('pointerdown', event => {
            if (event.target.closest('button')) return;
            drag = {x: event.clientX, y: event.clientY};
            canvas.setPointerCapture(event.pointerId);
        });
        canvas.addEventListener('pointermove', event => {
            if (!drag) return;
            move(drag.x - event.clientX, drag.y - event.clientY);
            drag = {x: event.clientX, y: event.clientY};
        });
        canvas.addEventListener('pointerup', () => { drag = null; });
        canvas.addEventListener('pointercancel', () => { drag = null; });
        searchArea.addEventListener('click', () => {
            const {width, height} = resize();
            const world = toWorld(center.lat, center.lng, zoom);
            const northWest = fromWorld(world.x - width / 2, Math.max(0, world.y - height / 2), zoom);
            const southEast = fromWorld(world.x + width / 2, Math.min(256 * 2 ** zoom, world.y + height / 2), zoom);
            const destination = new URL(window.location.href);
            destination.searchParams.set('north', String(clamp(northWest.lat, -90, 90)));
            destination.searchParams.set('south', String(clamp(southEast.lat, -90, 90)));
            destination.searchParams.set('west', String(northWest.lng));
            destination.searchParams.set('east', String(southEast.lng));
            destination.searchParams.delete('page');
            window.location.assign(destination.toString());
        });
        document.querySelector('[data-results-view="map"]')?.addEventListener('click', () => {
            isActive = true;
            requestAnimationFrame(render);
        });
        window.addEventListener('resize', () => { if (isActive) render(); });
    } else {
        mapRoot.classList.add('is-empty');
        mapRoot.textContent = 'Map coordinates are not available for these results. The list remains accessible.';
    }
}

document.querySelectorAll('[data-property-card]').forEach((item) => item.classList.remove('is-map-active'));
                document.querySelectorAll('.reserva-map-pin').forEach((item) => item.classList.remove('is-active'));

                pin.classList.add('is-active');
                card?.classList.add('is-map-active');

                if (card) {
                    listPane.hidden = false;
                    mapPane.hidden = true;
                    document.querySelector('[data-results-view="list"]')?.click();
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.querySelector('a,button')?.focus({ preventScroll: true });
                }
            });

            mapRoot.append(pin);
        });
    } else {
        mapRoot.classList.add('is-empty');
        mapRoot.textContent = 'Map coordinates are not available for these results.';
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
