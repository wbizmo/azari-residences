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

function showToast(message, type = 'information') {
    const region = document.querySelector('[data-toast-region]');

    if (!region || !message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `
        <span class="material-symbols-outlined" aria-hidden="true">
            ${type === 'success' ? 'check_circle' : 'info'}
        </span>
        <span>${message}</span>
        <button type="button" aria-label="Dismiss notification">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>
    `;

    const remove = () => toast.remove();
    toast.querySelector('button')?.addEventListener('click', remove);
    region.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('is-visible'));
    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(remove, 250);
    }, 5000);
}

document.querySelectorAll('[data-toast]').forEach((trigger) => {
    trigger.addEventListener('click', () => showToast(trigger.dataset.toast));
});

const header = document.querySelector('[data-site-header]');
const backToTop = document.querySelector('[data-back-to-top]');

function updateScrollState() {
    const hasScrolled = window.scrollY > 24;
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
    };

    const updateSummary = () => {
        const adults = Number(selector.querySelector('[data-guest-input="adults"]').value);
        const children = Number(selector.querySelector('[data-guest-input="children"]').value);

        summary.textContent = `${adults} ${adults === 1 ? 'adult' : 'adults'} · ${children} ${children === 1 ? 'child' : 'children'}`;
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
            const current = Number(input.value);
            const next = Math.min(limits[field].max, Math.max(limits[field].min, current + direction));

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

document.querySelectorAll('[data-availability-form]').forEach((form) => {
    const checkIn = form.querySelector('[name="check_in"]');
    const checkOut = form.querySelector('[name="check_out"]');

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
        }
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

/* AZARI_BACK_TO_TOP_REPAIR */
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
