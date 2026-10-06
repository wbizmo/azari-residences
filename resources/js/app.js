import './azari-user-area.js';
import './azari-frontend-ux.js';
import './reserva-marketplace.js';
import './reserva-property.js';
// import './bootstrap';

const body = document.body;
const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

function setBodyLock(locked) {
    body.classList.toggle('is-locked', locked);
}

function showToast(message, type = 'info') {
    const normalized = type === 'information' ? 'info' : type;
    if (typeof window.AzariToast === 'function') {
        window.AzariToast(message, normalized);
    }
}

function trackReservaEvent(name, detail = {}) {
    const safeDetail = Object.fromEntries(
        Object.entries(detail).filter(([, value]) =>
            ['string', 'number', 'boolean'].includes(typeof value) || value === null
        )
    );

    window.dispatchEvent(new CustomEvent('reserva:analytics', {
        detail: { name, ...safeDetail },
    }));

    if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({
            event: name,
            ...safeDetail,
        });
    }
}

function rememberReservaSearch(form) {
    try {
        const data = new FormData(form);
        const recent = {
            destination: String(data.get('destination') || ''),
            check_in: String(data.get('check_in') || ''),
            check_out: String(data.get('check_out') || ''),
            adults: Number(data.get('adults') || 1),
            children: Number(data.get('children') || 0),
            rooms: Number(data.get('rooms') || 1),
            saved_at: new Date().toISOString(),
        };

        localStorage.setItem('reserva:last-search', JSON.stringify(recent));
    } catch {
        // Storage can be disabled by the browser; booking search must still work.
    }
}

document.querySelectorAll('[data-toast]').forEach((trigger) => {
    trigger.addEventListener('click', () => showToast(trigger.dataset.toast));
});

const header = document.querySelector('[data-site-header]');
const backToTop = document.querySelector('[data-back-to-top]');

function updateScrollState() {
    const hasScrolled = window.scrollY > 0;
    header?.classList.toggle('is-scrolled', hasScrolled);
    backToTop?.classList.toggle('is-visible', window.scrollY > 500);
}

window.addEventListener('scroll', updateScrollState, { passive: true });
updateScrollState();

backToTop?.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

document.querySelectorAll('[data-popover]').forEach((popover) => {
    const trigger = popover.querySelector('[data-popover-trigger]');
    const panel = popover.querySelector('[data-popover-panel]');

    if (!trigger || !panel) {
        return;
    }

    const close = () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const shouldOpen = panel.hidden;
        document.querySelectorAll('[data-popover-panel]').forEach((otherPanel) => {
            otherPanel.hidden = true;
        });
        panel.hidden = !shouldOpen;
        trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!popover.contains(event.target)) {
            close();
        }
    });

    popover.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            trigger.focus();
        }
    });
});

function openLayer(layer, trigger) {
    if (!layer) {
        return;
    }

    layer.hidden = false;
    layer.setAttribute('aria-hidden', 'false');
    layer.dataset.returnFocus = trigger ? 'true' : 'false';
    layer.returnFocusElement = trigger;
    setBodyLock(true);

    window.requestAnimationFrame(() => {
        layer.classList.add('is-open');
        layer.querySelector(focusableSelector)?.focus();
    });
}

function closeLayer(layer) {
    if (!layer) {
        return;
    }

    layer.classList.remove('is-open');
    layer.setAttribute('aria-hidden', 'true');
    setBodyLock(false);

    window.setTimeout(() => {
        layer.hidden = true;
        layer.returnFocusElement?.focus();
    }, 220);
}

document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        openLayer(document.getElementById(trigger.dataset.modalOpen), trigger);
    });
});

document.querySelectorAll('[data-modal-close]').forEach((trigger) => {
    trigger.addEventListener('click', () => closeLayer(trigger.closest('[data-modal]')));
});

document.querySelectorAll('[data-drawer-open]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        openLayer(document.getElementById(trigger.dataset.drawerOpen), trigger);
    });
});

document.querySelectorAll('[data-drawer-close]').forEach((trigger) => {
    trigger.addEventListener('click', () => closeLayer(trigger.closest('[data-drawer]')));
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const openLayerElement = document.querySelector('[data-modal].is-open, [data-drawer].is-open');
    closeLayer(openLayerElement);
});

document.querySelectorAll('[data-demo-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        showToast(form.dataset.successMessage || 'Submitted successfully.', 'success');
        form.reset();

        const modal = form.closest('[data-modal]');
        if (modal) {
            closeLayer(modal);
        }
    });
});

document.querySelectorAll('[data-guest-selector]').forEach((selector) => {
    const trigger = selector.querySelector('[data-guest-trigger]');
    const panel = selector.querySelector('[data-guest-panel]');
    const summary = selector.querySelector('[data-guest-summary]');
    const done = selector.querySelector('[data-guest-done]');

    const limits = {
        adults: { min: 1, max: 12 },
        children: { min: 0, max: 8 },
        rooms: { min: 1, max: 20 },
    };

    const updateSummary = () => {
        const adultsInput = selector.querySelector('[data-guest-input="adults"]');
        const childrenInput = selector.querySelector('[data-guest-input="children"]');
        const roomsInput = selector.querySelector('[data-guest-input="rooms"]');

        const adults = Number(adultsInput?.value || 1);
        const children = Number(childrenInput?.value || 0);
        const rooms = Number(roomsInput?.value || 1);

        const parts = [
            `${adults} ${adults === 1 ? 'adult' : 'adults'}`,
            `${children} ${children === 1 ? 'child' : 'children'}`,
        ];

        if (roomsInput) {
            parts.push(`${rooms} ${rooms === 1 ? 'room' : 'rooms'}`);
        }

        summary.textContent = parts.join(' · ');
    };

    const close = () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        panel.hidden = !panel.hidden;
        trigger.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });

    selector.querySelectorAll('[data-stepper]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.dataset.stepper;
            const direction = Number(button.dataset.direction);
            const input = selector.querySelector(`[data-guest-input="${field}"]`);
            const output = selector.querySelector(`[data-count-for="${field}"]`);
            const limit = limits[field];

            if (!input || !output || !limit) {
                return;
            }

            const current = Number(input.value);
            const next = Math.min(limit.max, Math.max(limit.min, current + direction));

            input.value = String(next);
            output.textContent = String(next);
            updateSummary();
        });
    });

    done.addEventListener('click', () => {
        close();
        trigger.focus();
    });

    document.addEventListener('click', (event) => {
        if (!selector.contains(event.target)) {
            close();
        }
    });

    selector.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            trigger.focus();
        }
    });

    updateSummary();
});


document.querySelectorAll('[data-destination-search]').forEach((root) => {
    const input = root.querySelector('[data-destination-input]');
    const typeInput = root.querySelector('[data-destination-type]');
    const idInput = root.querySelector('[data-destination-id]');
    const list = root.querySelector('[data-destination-list]');
    const status = root.querySelector('[data-destination-status]');
    const suggestUrl = root.dataset.suggestUrl;

    if (!input || !typeInput || !idInput || !list || !suggestUrl) {
        return;
    }

    let timer = null;
    let controller = null;
    let requestSequence = 0;
    let activeIndex = -1;
    let options = [];

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };

    const setActive = (index) => {
        if (!options.length) {
            activeIndex = -1;
            return;
        }

        activeIndex = Math.max(0, Math.min(index, options.length - 1));

        options.forEach((option, optionIndex) => {
            const active = optionIndex === activeIndex;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        const active = options[activeIndex];
        if (active) {
            input.setAttribute('aria-activedescendant', active.id);
            active.scrollIntoView({ block: 'nearest' });
        }
    };

    const select = (item) => {
        input.value = item.value || item.label || '';
        typeInput.value = item.type || '';
        idInput.value = item.id ?? '';
        close();
        input.focus();
    };

    const render = (items) => {
        list.replaceChildren();
        options = [];

        items.forEach((item, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'reserva-destination-option';
            option.id = `reserva-destination-option-${index}-${Date.now()}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');

            const label = document.createElement('strong');
            label.textContent = item.label || item.value || '';

            const meta = document.createElement('span');
            meta.textContent = [item.secondary, item.type].filter(Boolean).join(' · ');

            option.append(label, meta);
            option.addEventListener('click', () => select(item));
            list.append(option);
            options.push(option);
        });

        if (!options.length) {
            close();
            if (status) {
                status.textContent = 'No matching destinations.';
            }
            return;
        }

        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        if (status) {
            status.textContent = `${options.length} destination ${options.length === 1 ? 'suggestion' : 'suggestions'} available.`;
        }
    };

    const search = async () => {
        const query = input.value.trim();
        typeInput.value = '';
        idInput.value = '';

        if (query.length < 2) {
            controller?.abort();
            close();
            if (status) {
                status.textContent = '';
            }
            return;
        }

        controller?.abort();
        controller = new AbortController();
        const sequence = ++requestSequence;

        try {
            const url = new URL(suggestUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('limit', '8');

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Destination search failed with ${response.status}`);
            }

            const payload = await response.json();

            if (sequence !== requestSequence || input.value.trim() !== query) {
                return;
            }

            render(Array.isArray(payload.data) ? payload.data : []);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            close();
            if (status) {
                status.textContent = 'Destination suggestions are temporarily unavailable. You can still search with the text you entered.';
            }
        }
    };

    input.addEventListener('input', () => {
        typeInput.value = '';
        idInput.value = '';
        window.clearTimeout(timer);
        timer = window.setTimeout(search, 180);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            if (!list.hidden && options.length) {
                event.preventDefault();
                setActive(activeIndex < 0 ? 0 : activeIndex + 1);
            }
        } else if (event.key === 'ArrowUp') {
            if (!list.hidden && options.length) {
                event.preventDefault();
                setActive(activeIndex <= 0 ? options.length - 1 : activeIndex - 1);
            }
        } else if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
            event.preventDefault();
            options[activeIndex].click();
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (!root.contains(document.activeElement)) {
                close();
            }
        }, 120);
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });
});

document.querySelectorAll('[data-availability-form]').forEach((form) => {
    const checkIn = form.querySelector('[name="check_in"]');
    const checkOut = form.querySelector('[name="check_out"]');
    let reservaSearchStarted = false;

    form.addEventListener('focusin', () => {
        if (reservaSearchStarted) {
            return;
        }

        reservaSearchStarted = true;
        trackReservaEvent('search_started', {
            surface: form.closest('.availability-section') ? 'homepage' : 'availability',
        });
    }, { once: false });

    const clearErrors = () => {
        form.querySelectorAll('.field-error').forEach((error) => {
            error.textContent = '';
        });
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
            field.removeAttribute('aria-invalid');
        });
    };

    const setError = (field, message) => {
        const error = form.querySelector(`[data-error-for="${field.name}"]`);
        field.setAttribute('aria-invalid', 'true');

        if (error) {
            error.textContent = message;
        }
    };

    checkIn.addEventListener('change', () => {
        if (!checkIn.value) {
            return;
        }

        const nextDay = new Date(`${checkIn.value}T00:00:00`);
        nextDay.setDate(nextDay.getDate() + 1);
        const minimumCheckout = nextDay.toISOString().slice(0, 10);
        checkOut.min = minimumCheckout;

        if (checkOut.value && checkOut.value < minimumCheckout) {
            checkOut.value = minimumCheckout;
        }
    });

    form.addEventListener('submit', (event) => {
        clearErrors();
        let valid = true;

        if (!checkIn.value) {
            setError(checkIn, 'Choose a check-in date.');
            valid = false;
        }

        if (!checkOut.value) {
            setError(checkOut, 'Choose a check-out date.');
            valid = false;
        } else if (checkIn.value && checkOut.value <= checkIn.value) {
            setError(checkOut, 'Check-out must be after check-in.');
            valid = false;
        }

        if (!valid) {
            event.preventDefault();
            form.querySelector('[aria-invalid="true"]')?.focus();
            showToast('Please review the highlighted booking-search fields.');
            return;
        }

        const adults = Number(form.querySelector('[name="adults"]')?.value || 1);
        const children = Number(form.querySelector('[name="children"]')?.value || 0);
        const rooms = Number(form.querySelector('[name="rooms"]')?.value || 1);

        trackReservaEvent('search_submitted', {
            surface: form.closest('.availability-section') ? 'homepage' : 'availability',
            adults,
            children,
            rooms,
            has_destination: Boolean(form.querySelector('[name="destination"]')?.value?.trim()),
        });

        rememberReservaSearch(form);
    });
});

const publicPreloader = document.querySelector('[data-public-preloader]');
if (publicPreloader) {
    const hidePreloader = () => {
        publicPreloader.classList.add('is-hidden');
        window.setTimeout(() => publicPreloader.remove(), 500);
    };

    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader, { once: true });
        window.setTimeout(hidePreloader, 4500);
    }
}

document.querySelector('[data-generate-password]')?.addEventListener('click', () => {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    const values = new Uint32Array(18);
    crypto.getRandomValues(values);
    const password = Array.from(values, value => alphabet[value % alphabet.length]).join('');

    const field = document.getElementById('staff-password');
    const confirmation = document.getElementById('staff-password-confirmation');

    if (field && confirmation) {
        field.value = password;
        confirmation.value = password;
    }
});

/* Azari global back-to-top behaviour */
document.addEventListener('DOMContentLoaded', () => {
    const controls = document.querySelectorAll(
        '[data-back-to-top], .back-to-top, #back-to-top'
    );

    if (!controls.length) {
        return;
    }

    const updateVisibility = () => {
        const visible = window.scrollY > 420;

        controls.forEach((control) => {
            control.classList.toggle('is-visible', visible);
            control.setAttribute('aria-hidden', visible ? 'false' : 'true');
            control.tabIndex = visible ? 0 : -1;
        });
    };

    controls.forEach((control) => {
        control.setAttribute('type', 'button');
        control.setAttribute('aria-label', 'Back to top');

        control.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    ? 'auto'
                    : 'smooth',
            });
        });
    });

    updateVisibility();

    window.addEventListener('scroll', updateVisibility, {
        passive: true,
    });
});

/* RESAVAR_BACK_TO_TOP_REPAIR */
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll(
        '[data-back-to-top], .back-to-top, #back-to-top'
    );

    if (!buttons.length) {
        return;
    }

    const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    );

    const updateBackToTopVisibility = () => {
        const shouldShow = window.scrollY > 420;

        buttons.forEach((button) => {
            button.classList.toggle('is-visible', shouldShow);
            button.setAttribute(
                'aria-hidden',
                shouldShow ? 'false' : 'true'
            );

            button.tabIndex = shouldShow ? 0 : -1;
        });
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: reducedMotion.matches ? 'auto' : 'smooth',
            });
        });
    });

    updateBackToTopVisibility();

    window.addEventListener(
        'scroll',
        updateBackToTopVisibility,
        { passive: true }
    );
});

/* AZARI_PRELOADER_JS_START */

const hideAzariPreloader = () => {
    const preloaders = document.querySelectorAll('[data-public-preloader]');

    preloaders.forEach((preloader) => {
        preloader.classList.add('is-hidden');

        window.setTimeout(() => {
            preloader.remove();
        }, 350);
    });
};

if (document.readyState === 'complete') {
    hideAzariPreloader();
} else {
    window.addEventListener('load', hideAzariPreloader, {
        once: true,
    });

    window.setTimeout(hideAzariPreloader, 5000);
}

/* AZARI_PRELOADER_JS_END */

/* AZARI_FINAL_PRELOADER_JS_START */

const dismissPublicPreloader = () => {
    document
        .querySelectorAll('[data-public-preloader]')
        .forEach((preloader) => {
            if (preloader.dataset.dismissed === 'true') {
                return;
            }

            preloader.dataset.dismissed = 'true';
            preloader.classList.add('is-hidden');

            window.setTimeout(() => {
                preloader.remove();
            }, 350);
        });
};

if (document.readyState === 'complete') {
    dismissPublicPreloader();
} else {
    window.addEventListener('load', dismissPublicPreloader, {
        once: true,
    });
}

window.setTimeout(dismissPublicPreloader, 5000);

/* AZARI_FINAL_PRELOADER_JS_END */

/* AZARI_SPRINT_03_04_ADMIN_START */

document.querySelectorAll('[data-admin-tabs]').forEach((tabs) => {
    const buttons = [...tabs.querySelectorAll('[data-tab-target]')];
    const panels = [...document.querySelectorAll('[data-tab-panel]')];

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.tabTarget;

            buttons.forEach((item) => item.classList.toggle('is-active', item === button));
            panels.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.tabPanel === target);
            });
        });
    });
});

document.querySelectorAll('.az-upload input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
        const label = input.closest('.az-upload')?.querySelector('[data-file-label]');
        if (!label) return;

        const files = [...input.files];
        label.textContent = files.length === 0
            ? 'Select a file'
            : files.length === 1
                ? files[0].name
                : `${files.length} files selected`;
    });
});

document.querySelectorAll('.az-color-control input[type="color"]').forEach((input) => {
    const output = input.closest('.az-color-control')?.querySelector('output');
    input.addEventListener('input', () => {
        if (output) output.textContent = input.value.toUpperCase();
    });
});

document.querySelectorAll('.az-range-field input[type="range"]').forEach((input) => {
    const output = input.closest('.az-range-field')?.querySelector('output');
    const update = () => {
        if (!output) return;
        output.textContent = `${input.value}${input.name === 'hero_overlay' ? '%' : 'px'}`;
    };
    input.addEventListener('input', update);
    update();
});

document.querySelectorAll('[data-sortable]').forEach((container) => {
    let dragging = null;

    const items = () => [...container.querySelectorAll('[data-sort-id]')];

    items().forEach((item) => {
        item.addEventListener('dragstart', () => {
            dragging = item;
            item.classList.add('is-dragging');
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('is-dragging');
            dragging = null;
        });

        item.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!dragging || dragging === item) return;

            const bounds = item.getBoundingClientRect();
            const after = event.clientY > bounds.top + bounds.height / 2;
            container.insertBefore(dragging, after ? item.nextSibling : item);
        });
    });

    const form = container.closest('[data-sort-form]');
    form?.addEventListener('submit', () => {
        const holder = form.querySelector('[data-sort-inputs]');
        if (!holder) return;

        holder.innerHTML = '';
        items().forEach((item) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[]';
            input.value = item.dataset.sortId;
            holder.appendChild(input);
        });
    });
});

/* AZARI_SPRINT_03_04_ADMIN_END */

/* AZARI_ADMIN_EXPERIENCE_START */
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const sidebarOpen = document.querySelector('[data-sidebar-open]');
    const sidebarClose = document.querySelector('[data-sidebar-close]');
    const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');

    const openSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.add('is-open');
        sidebarBackdrop?.removeAttribute('hidden');
        sidebarOpen?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    };

    const closeSidebar = () => {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        sidebarBackdrop?.setAttribute('hidden', '');
        sidebarOpen?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    };

    sidebarOpen?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarBackdrop?.addEventListener('click', closeSidebar);

    document.querySelectorAll('.az-nav-link').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 900px)').matches) {
                closeSidebar();
            }
        });
    });

    const profileMenu = document.querySelector('[data-profile-menu]');
    const profileTrigger = document.querySelector('[data-profile-trigger]');
    const profileDropdown = document.querySelector('[data-profile-dropdown]');

    const closeProfile = () => {
        profileDropdown?.setAttribute('hidden', '');
        profileTrigger?.setAttribute('aria-expanded', 'false');
    };

    const openProfile = () => {
        profileDropdown?.removeAttribute('hidden');
        profileTrigger?.setAttribute('aria-expanded', 'true');
        profileDropdown?.querySelector('a, button')?.focus();
    };

    profileTrigger?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = profileTrigger.getAttribute('aria-expanded') === 'true';
        isOpen ? closeProfile() : openProfile();
    });

    profileDropdown?.addEventListener('click', (event) => event.stopPropagation());
    document.addEventListener('click', closeProfile);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeProfile();
            closeSidebar();
            profileTrigger?.focus();
        }
    });

    const themeToggle = document.querySelector('[data-theme-toggle]');
    const root = document.documentElement;
    const savedTheme = localStorage.getItem('azari-admin-theme');

    if (savedTheme === 'dark' || savedTheme === 'light') {
        root.dataset.adminTheme = savedTheme;
    }

    const updateThemeIcon = () => {
        const icon = themeToggle?.querySelector('.material-symbols-outlined');
        if (!icon) return;
        icon.textContent = root.dataset.adminTheme === 'dark' ? 'light_mode' : 'dark_mode';
    };

    updateThemeIcon();

    themeToggle?.addEventListener('click', () => {
        const nextTheme = root.dataset.adminTheme === 'dark' ? 'light' : 'dark';
        root.dataset.adminTheme = nextTheme;
        localStorage.setItem('azari-admin-theme', nextTheme);
        updateThemeIcon();
    });

    const searchInput = document.querySelector('.az-admin-search input');

    document.addEventListener('keydown', (event) => {
        const shortcut = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';

        if (shortcut && searchInput) {
            event.preventDefault();
            searchInput.focus();
        }
    });
});
/* AZARI_ADMIN_EXPERIENCE_END */

import './azari-interactions';

import './azari-production-hotfix';

import './azari-date-picker-v3';

document.querySelectorAll('[data-server-toast]').forEach((toast) => {
    const remove = () => toast.remove();
    toast.querySelector('button')?.addEventListener('click', remove);
    window.setTimeout(remove, 6000);
});

/*
 * Permanent Resavar public-header state.
 * Non-hero pages always use the readable solid dark-green treatment.
 * Hero pages may use their intentional overlay state only while the hero
 * physically remains beneath the header.
 */
const initialiseAzariPublicHeader = () => {
    const body = document.body;
    const hero = document.querySelector('.azari-home-hero');
    const header = document.querySelector(
        '.site-header, .public-site-header, .azari-site-header, [data-site-header]'
    );

    if (!body || !header) {
        return;
    }

    const updateHeaderState = () => {
        if (!hero) {
            body.classList.add('azari-solid-header');
            body.classList.remove('azari-header-over-hero');

            return;
        }

        const heroBottom = hero.getBoundingClientRect().bottom;
        const headerHeight = header.getBoundingClientRect().height;
        const isOverHero = heroBottom > headerHeight + 12;

        body.classList.toggle('azari-header-over-hero', isOverHero);
        body.classList.toggle('azari-solid-header', !isOverHero);
    };

    updateHeaderState();

    window.addEventListener('scroll', updateHeaderState, {
        passive: true,
    });

    window.addEventListener('resize', updateHeaderState);
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initialiseAzariPublicHeader,
        { once: true }
    );
} else {
    initialiseAzariPublicHeader();
}

// RESAVAR HOTFIX: icons must be visible immediately; font loading may enhance later.
document.documentElement.classList.add('az-icons-ready');
document.body?.classList.toggle(
    'azari-home-page',
    window.location.pathname === '/' || window.location.pathname === ''
);

/*
|--------------------------------------------------------------------------
| Resavar public-header single-logo controller
|--------------------------------------------------------------------------
|
| There is only one physical <img> element in the public header.
| This script changes only its src, so the logo position and navigation
| layout never move.
|
*/

document.addEventListener('DOMContentLoaded', () => {
    const logo = document.querySelector('[data-azari-public-logo]');

    if (!logo) {
        return;
    }

    const darkLogo = logo.dataset.darkLogo;
    const lightLogo = logo.dataset.lightLogo;

    const normalizedPath = window.location.pathname.replace(/\/+$/, '');
    const isHomepage = normalizedPath === '';

    const updateAzariPublicLogo = () => {
        const hasScrolled = window.scrollY > 0;

        /*
         * Homepage:
         *   top       -> logo-dark.png
         *   scrolled  -> logo-light.png
         *
         * Other public pages:
         *   always    -> logo-light.png
         */
        const requiredLogo =
            isHomepage && !hasScrolled
                ? darkLogo
                : lightLogo;

        if (
            requiredLogo &&
            logo.getAttribute('src') !== requiredLogo
        ) {
            logo.setAttribute('src', requiredLogo);
        }

        logo.dataset.logoVariant =
            isHomepage && !hasScrolled
                ? 'dark'
                : 'light';
    };

    updateAzariPublicLogo();

    window.addEventListener(
        'scroll',
        updateAzariPublicLogo,
        { passive: true }
    );
});


/*
|--------------------------------------------------------------------------
| Resavar public hamburger colour controller
|--------------------------------------------------------------------------
|
| Homepage:
| - At the top: semi-peach
| - After scrolling: Azari green
|
| Other public pages:
| - Always Azari green
|
| This changes only a state attribute. It does not add, hide, duplicate,
| replace, or reposition the hamburger button.
|
*/

document.addEventListener('DOMContentLoaded', () => {
    const normalizedPath = window.location.pathname.replace(/\/+$/, '');
    const isHomepage = normalizedPath === '';

    const updateAzariHamburgerColour = () => {
        const hasScrolled = window.scrollY > 0;

        const variant =
            isHomepage && !hasScrolled
                ? 'peach'
                : 'green';

        document.documentElement.dataset.azariHamburgerVariant = variant;
    };

    updateAzariHamburgerColour();

    window.addEventListener(
        'scroll',
        updateAzariHamburgerColour,
        { passive: true }
    );
});


/*
|--------------------------------------------------------------------------
| Resavar public header visual state
|--------------------------------------------------------------------------
|
| One shared state controls the public logo and hamburger.
|
| home-top:
|   - URL path is /
|   - scroll position is at the top of the page
|   - hamburger is #FFFFFF
|
| solid:
|   - homepage has been scrolled
|   - or the current page is not the homepage
|   - hamburger uses its original Azari green
|
*/

document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.querySelector('[data-azari-menu-toggle]');
    const publicLogo = document.querySelector('[data-azari-public-logo]');

    const normalizedPath =
        window.location.pathname.replace(/\/+$/, '');

    const isHomepage = normalizedPath === '';

    const updateAzariPublicHeaderState = () => {
        const isHomepageTop =
            isHomepage &&
            window.scrollY <= 0;

        document.documentElement.dataset.azariPublicHeaderState =
            isHomepageTop
                ? 'home-top'
                : 'solid';

        /*
         * Keep the existing single-logo implementation synchronized.
         * This changes only the src of the one existing image node.
         */
        if (publicLogo) {
            const requiredLogo =
                isHomepageTop
                    ? publicLogo.dataset.darkLogo
                    : publicLogo.dataset.lightLogo;

            if (
                requiredLogo &&
                publicLogo.getAttribute('src') !== requiredLogo
            ) {
                publicLogo.setAttribute('src', requiredLogo);
            }
        }
    };

    updateAzariPublicHeaderState();

    window.addEventListener(
        'scroll',
        updateAzariPublicHeaderState,
        { passive: true }
    );
});



/* AZARI_AUTH_PASSWORD_HARD_FIX_START */
const initialiseAzariPasswordControls = () => {
    document.querySelectorAll('[data-azari-password-control]').forEach((control) => {
        const input = control.querySelector('input');
        const toggle = control.querySelector('[data-azari-password-toggle]');
        const eyeOpen = control.querySelector('[data-azari-eye-open]');
        const eyeClosed = control.querySelector('[data-azari-eye-closed]');

        if (!input || !toggle || toggle.dataset.ready === 'true') {
            return;
        }

        toggle.dataset.ready = 'true';

        /*
         * Prevent the toggle from taking focus away from the input.
         * There is no manual refocus, no caret restoration and no layout work.
         */
        toggle.addEventListener('pointerdown', (event) => {
            event.preventDefault();
        });

        toggle.addEventListener('click', () => {
            const willShow = input.type === 'password';

            input.type = willShow ? 'text' : 'password';

            toggle.setAttribute(
                'aria-pressed',
                willShow ? 'true' : 'false'
            );

            toggle.setAttribute(
                'aria-label',
                `${willShow ? 'Hide' : 'Show'} ${
                    input.getAttribute('aria-label') ||
                    toggle.getAttribute('aria-controls')?.includes('confirmation')
                        ? 'password confirmation'
                        : 'password'
                }`
            );
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initialiseAzariPasswordControls,
        { once: true }
    );
} else {
    initialiseAzariPasswordControls();
}
/* AZARI_AUTH_PASSWORD_HARD_FIX_END */

/* AZARI_RESPONSIVE_TABLE_HARDENING */
const initialiseAzariResponsiveTables = () => {
    document.querySelectorAll('.az-admin-content table, .az-user-content table').forEach((table) => {
        if (table.closest('.az-responsive-table-shell, .az-admin-table-wrap, .az-s78-table-wrap, .table-responsive, .overflow-x-auto')) return;
        const shell = document.createElement('div');
        shell.className = 'az-responsive-table-shell';
        shell.setAttribute('role', 'region');
        shell.setAttribute('aria-label', table.getAttribute('aria-label') || 'Scrollable data table');
        shell.setAttribute('tabindex', '0');
        table.parentNode.insertBefore(shell, table);
        shell.appendChild(table);
    });
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialiseAzariResponsiveTables, { once: true });
else initialiseAzariResponsiveTables();


// RESAVAR_BOOKING_FRONTEND_PARITY_V1
(() => {
    const KEYS = {
        searches: 'resavar:recent-searches:v1',
        favourites: 'resavar:local-favourites:v1',
        compare: 'resavar:compare:v1',
        viewed: 'resavar:recent-viewed:v1',
    };

    const read = (key) => {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(value) ? value : [];
        } catch {
            return [];
        }
    };

    const write = (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch {}
    };

    const safePath = (value) => {
        try {
            const url = new URL(String(value || ''), window.location.origin);
            return url.origin === window.location.origin ? `${url.pathname}${url.search}` : '/';
        } catch {
            return '/';
        }
    };

    const cleanText = (value, max = 160) => String(value || '').trim().slice(0, max);

    document.querySelectorAll('form[data-availability-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const data = new FormData(form);
            const params = new URLSearchParams();
            data.forEach((value, key) => {
                if (typeof value === 'string' && value !== '') params.append(key, value);
            });

            const item = {
                destination: cleanText(data.get('destination') || 'Any destination', 120),
                checkIn: cleanText(data.get('check_in'), 10),
                checkOut: cleanText(data.get('check_out'), 10),
                adults: Math.max(1, Number(data.get('adults') || 1)),
                children: Math.max(0, Number(data.get('children') || 0)),
                rooms: Math.max(1, Number(data.get('rooms') || 1)),
                url: safePath(`${form.action}?${params.toString()}`),
                savedAt: Date.now(),
            };

            const prior = read(KEYS.searches).filter((entry) => entry && entry.url !== item.url);
            write(KEYS.searches, [item, ...prior].slice(0, 5));
        });

        const recent = read(KEYS.searches).filter((entry) => entry && entry.url).slice(0, 4);
        if (!recent.length || form.parentElement?.querySelector('[data-resavar-recent-searches]')) return;

        const box = document.createElement('section');
        box.className = 'resavar-recent-searches';
        box.dataset.resavarRecentSearches = '';
        box.setAttribute('aria-label', 'Recent searches');

        const head = document.createElement('div');
        head.className = 'resavar-recent-searches__head';
        const title = document.createElement('strong');
        title.textContent = 'Recent searches';
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'button button-secondary';
        clear.textContent = 'Clear';
        clear.addEventListener('click', () => {
            write(KEYS.searches, []);
            box.remove();
        });
        head.append(title, clear);

        const list = document.createElement('div');
        list.className = 'resavar-recent-searches__list';

        recent.forEach((entry) => {
            const link = document.createElement('a');
            link.className = 'resavar-recent-search';
            link.href = safePath(entry.url);

            const strong = document.createElement('strong');
            strong.textContent = cleanText(entry.destination || 'Any destination');

            const small = document.createElement('small');
            small.textContent = [entry.checkIn, entry.checkOut].filter(Boolean).join(' → ') ||
                `${entry.adults || 1} guest${Number(entry.adults || 1) === 1 ? '' : 's'}`;

            link.append(strong, small);
            list.append(link);
        });

        box.append(head, list);
        form.insertAdjacentElement('afterend', box);
    });

    const syncFavouriteButtons = () => {
        const saved = new Set(read(KEYS.favourites).map((item) => String(item.id)));
        document.querySelectorAll('[data-resavar-local-favourite]').forEach((button) => {
            const active = saved.has(String(button.dataset.resavarLocalFavourite));
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            const icon = button.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = active ? 'favorite' : 'favorite_border';
            const label = button.querySelector('[data-favourite-label]');
            if (label) label.textContent = active ? 'Saved' : 'Save';
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-resavar-local-favourite]');
        if (!button) return;

        const id = cleanText(button.dataset.resavarLocalFavourite, 64);
        if (!id) return;

        const current = read(KEYS.favourites);
        const index = current.findIndex((item) => String(item.id) === id);

        if (index >= 0) {
            current.splice(index, 1);
        } else {
            current.unshift({
                id,
                name: cleanText(button.dataset.propertyName),
                url: safePath(button.dataset.propertyUrl),
                savedAt: Date.now(),
            });
        }

        write(KEYS.favourites, current.slice(0, 50));
        syncFavouriteButtons();
    });

    syncFavouriteButtons();

    let compare = read(KEYS.compare).filter((item) => item && item.id && item.url).slice(0, 3);
    const tray = document.createElement('aside');
    tray.className = 'resavar-compare-tray';
    tray.hidden = true;
    tray.setAttribute('aria-label', 'Compare selected stays');
    document.body.append(tray);

    const renderCompare = () => {
        compare = compare.slice(0, 3);
        write(KEYS.compare, compare);
        tray.replaceChildren();
        tray.hidden = compare.length === 0;

        document.querySelectorAll('[data-resavar-compare]').forEach((button) => {
            const active = compare.some((item) => String(item.id) === String(button.dataset.resavarCompare));
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.textContent = active ? 'Added to compare' : 'Compare';
        });

        if (!compare.length) return;

        const head = document.createElement('div');
        head.className = 'resavar-compare-tray__head';

        const strong = document.createElement('strong');
        strong.textContent = `Compare stays (${compare.length}/3)`;

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'button button-secondary';
        clear.textContent = 'Clear';
        clear.addEventListener('click', () => {
            compare = [];
            renderCompare();
        });

        head.append(strong, clear);

        const items = document.createElement('div');
        items.className = 'resavar-compare-tray__items';

        compare.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'resavar-compare-tray__item';

            const link = document.createElement('a');
            link.href = safePath(item.url);
            link.textContent = cleanText(item.name || 'Stay');

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'resavar-compare-tray__remove';
            remove.setAttribute('aria-label', `Remove ${cleanText(item.name || 'stay')} from comparison`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                compare = compare.filter((entry) => String(entry.id) !== String(item.id));
                renderCompare();
            });

            row.append(link, remove);
            items.append(row);
        });

        tray.append(head, items);
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-resavar-compare]');
        if (!button) return;

        const id = cleanText(button.dataset.resavarCompare, 64);
        if (!id) return;

        if (compare.some((item) => String(item.id) === id)) {
            compare = compare.filter((item) => String(item.id) !== id);
        } else if (compare.length < 3) {
            compare.push({
                id,
                name: cleanText(button.dataset.propertyName),
                url: safePath(button.dataset.propertyUrl),
            });
        } else {
            showToast('You can compare up to three stays at a time.', 'info');
        }

        renderCompare();
    });

    renderCompare();

    const property = document.querySelector('[data-resavar-property]');
    if (property) {
        const item = {
            id: cleanText(property.dataset.resavarProperty, 64),
            name: cleanText(property.dataset.propertyName),
            url: safePath(property.dataset.propertyUrl),
            location: cleanText(property.dataset.propertyLocation),
            image: safePath(property.dataset.propertyImage),
            viewedAt: Date.now(),
        };

        if (item.id) {
            const prior = read(KEYS.viewed).filter((entry) => String(entry.id) !== item.id);
            write(KEYS.viewed, [item, ...prior].slice(0, 8));
        }
    }

    const recentHost = document.querySelector('[data-resavar-recent-viewed-host]');
    const recentList = recentHost?.querySelector('[data-resavar-recent-viewed-list]');

    if (recentHost && recentList) {
        const viewed = read(KEYS.viewed).filter((item) => item && item.id && item.url).slice(0, 6);

        viewed.forEach((item) => {
            const link = document.createElement('a');
            link.className = 'resavar-recent-viewed__card';
            link.href = safePath(item.url);

            const img = document.createElement('img');
            img.src = safePath(item.image);
            img.alt = '';
            img.loading = 'lazy';

            const copy = document.createElement('span');
            const strong = document.createElement('strong');
            strong.textContent = cleanText(item.name || 'Stay');

            const small = document.createElement('small');
            small.textContent = cleanText(item.location || 'Recently viewed');

            copy.append(strong, small);
            link.append(img, copy);
            recentList.append(link);
        });

        recentHost.hidden = viewed.length === 0;
    }
})();
// RESAVAR_BOOKING_FRONTEND_PARITY_V1_END
