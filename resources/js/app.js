import './azari-user-area.js';
import './azari-frontend-ux.js';
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
