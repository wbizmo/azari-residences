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
        points = JSON.parse(source?.textContent || '[]');
    } catch {
        points = [];
    }

    if (points.length) {
        const lats = points.map((point) => Number(point.lat)).filter(Number.isFinite);
        const lngs = points.map((point) => Number(point.lng)).filter(Number.isFinite);
        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);
        const latSpan = Math.max(0.0001, maxLat - minLat);
        const lngSpan = Math.max(0.0001, maxLng - minLng);

        points.forEach((point) => {
            const lat = Number(point.lat);
            const lng = Number(point.lng);

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            const pin = document.createElement('button');
            pin.type = 'button';
            pin.className = 'reserva-map-pin';
            pin.dataset.propertyId = String(point.id);
            pin.style.left = `${8 + ((lng - minLng) / lngSpan) * 84}%`;
            pin.style.top = `${8 + ((maxLat - lat) / latSpan) * 84}%`;
            pin.textContent = `${point.currency} ${Number(point.price).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
            pin.setAttribute('aria-label', `${point.name}, ${point.currency} ${point.price}`);

            pin.addEventListener('click', () => {
                const card = document.querySelector(`[data-property-card="${point.id}"]`);
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
