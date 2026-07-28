const SELECTORS = {
    submitter: 'button[type="submit"], input[type="submit"]',
    validationError: '[data-validation-error]',
};

const state = new WeakMap();

function toast(message, type = 'info') {
    if (!message) return;

    if (typeof window.AzariToast === 'function') {
        window.AzariToast(message, type);
        return;
    }

    window.dispatchEvent(new CustomEvent('azari:toast', {
        detail: { message, type },
    }));
}

function ensureConfirmationModal() {
    let modal = document.getElementById('azari-global-confirmation');

    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'azari-global-confirmation';
    modal.className = 'azari-confirmation-layer';
    modal.hidden = true;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'azari-confirmation-title');
    modal.innerHTML = `
        <div class="azari-confirmation-backdrop" data-confirmation-cancel></div>
        <section class="azari-confirmation-card" tabindex="-1">
            <button
                class="azari-confirmation-close"
                type="button"
                aria-label="Close confirmation"
                data-confirmation-cancel
            >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
            <div class="azari-confirmation-icon" aria-hidden="true">
                <span class="material-symbols-outlined">help</span>
            </div>
            <h2 id="azari-confirmation-title">Please confirm</h2>
            <p data-confirmation-message></p>
            <div class="azari-confirmation-actions">
                <button type="button" class="azari-confirmation-secondary" data-confirmation-cancel>
                    Cancel
                </button>
                <button type="button" class="azari-confirmation-primary" data-confirmation-accept>
                    Continue
                </button>
            </div>
        </section>
    `;

    document.body.appendChild(modal);
    return modal;
}

function requestConfirmation(message, {
    confirmLabel = 'Continue',
    cancelLabel = 'Cancel',
    destructive = false,
} = {}) {
    return new Promise((resolve) => {
        const modal = ensureConfirmationModal();
        const card = modal.querySelector('.azari-confirmation-card');
        const messageNode = modal.querySelector('[data-confirmation-message]');
        const accept = modal.querySelector('[data-confirmation-accept]');
        const cancelButtons = [...modal.querySelectorAll('[data-confirmation-cancel]')];
        const previouslyFocused = document.activeElement;

        messageNode.textContent = message;
        accept.textContent = confirmLabel;
        accept.classList.toggle('is-destructive', destructive);
        cancelButtons
            .filter((button) => button.tagName === 'BUTTON')
            .forEach((button) => {
                if (!button.classList.contains('azari-confirmation-close')) {
                    button.textContent = cancelLabel;
                }
            });

        const close = (result) => {
            modal.hidden = true;
            document.body.classList.remove('is-locked');
            document.removeEventListener('keydown', onKeydown);
            accept.removeEventListener('click', onAccept);
            cancelButtons.forEach((button) => button.removeEventListener('click', onCancel));
            previouslyFocused?.focus?.();
            resolve(result);
        };

        const onAccept = () => close(true);
        const onCancel = () => close(false);
        const onKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(false);
                return;
            }

            if (event.key !== 'Tab') return;

            const focusable = [...card.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )];

            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        accept.addEventListener('click', onAccept);
        cancelButtons.forEach((button) => button.addEventListener('click', onCancel));
        document.addEventListener('keydown', onKeydown);

        modal.hidden = false;
        document.body.classList.add('is-locked');
        window.requestAnimationFrame(() => card.focus());
    });
}

function setBusy(form, busy, submitter = null) {
    if (!(form instanceof HTMLFormElement)) return;

    const controls = [...form.querySelectorAll(SELECTORS.submitter)];
    const current = state.get(form) || {
        disabled: new Map(),
        labels: new Map(),
        timeout: null,
    };

    if (busy) {
        window.clearTimeout(current.timeout);
        form.setAttribute('aria-busy', 'true');
        form.classList.add('is-submitting');

        controls.forEach((control) => {
            current.disabled.set(control, control.disabled);
            current.labels.set(control, control instanceof HTMLInputElement
                ? control.value
                : control.innerHTML);

            control.disabled = true;
        });

        if (submitter) {
            const label = submitter.dataset.loadingLabel || form.dataset.loadingLabel || 'Please wait…';
            if (submitter instanceof HTMLInputElement) {
                submitter.value = label;
            } else {
                submitter.textContent = label;
            }
        }

        current.timeout = window.setTimeout(() => setBusy(form, false), 30000);
        state.set(form, current);
        return;
    }

    form.removeAttribute('aria-busy');
    form.classList.remove('is-submitting');
    window.clearTimeout(current.timeout);

    controls.forEach((control) => {
        control.disabled = current.disabled.get(control) ?? false;
        const label = current.labels.get(control);

        if (label === undefined) return;

        if (control instanceof HTMLInputElement) {
            control.value = label;
        } else {
            control.innerHTML = label;
        }
    });

    state.delete(form);
}

function clearValidation(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
        field.removeAttribute('aria-invalid');
    });

    form.querySelectorAll(SELECTORS.validationError).forEach((node) => {
        node.textContent = '';
        node.hidden = true;
    });
}

function applyValidation(form, errors = {}) {
    clearValidation(form);
    let firstInvalid = null;

    Object.entries(errors).forEach(([name, messages]) => {
        const field = form.elements.namedItem(name);
        const message = Array.isArray(messages) ? messages[0] : String(messages ?? '');

        if (field instanceof HTMLElement) {
            field.setAttribute('aria-invalid', 'true');
            firstInvalid ||= field;
        }

        const target = form.querySelector(
            `[data-validation-error="${CSS.escape(name)}"]`
        );

        if (target) {
            target.textContent = message;
            target.hidden = false;
        }
    });

    firstInvalid?.focus();
}

async function submitAsync(form, submitter) {
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const formData = new FormData(form);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (csrf) headers['X-CSRF-TOKEN'] = csrf;

    setBusy(form, true, submitter);
    clearValidation(form);

    try {
        const response = await fetch(form.action || window.location.href, {
            method,
            body: method === 'GET' ? null : formData,
            headers,
            credentials: 'same-origin',
        });

        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json')
            ? await response.json()
            : { message: await response.text() };

        if (response.status === 422 && payload.errors) {
            applyValidation(form, payload.errors);
            toast(payload.message || 'Please review the highlighted fields.', 'error');
            return;
        }

        if (!response.ok) {
            throw new Error(payload.message || 'The request could not be completed.');
        }

        toast(payload.message || form.dataset.successMessage || 'Saved successfully.', 'success');

        form.dispatchEvent(new CustomEvent('azari:form-success', {
            bubbles: true,
            detail: payload,
        }));

        if (form.hasAttribute('data-reset-on-success')) form.reset();
        if (payload.redirect) window.location.assign(payload.redirect);
    } catch (error) {
        const message = error instanceof Error
            ? error.message
            : 'The request could not be completed.';

        toast(message, 'error');
        form.dispatchEvent(new CustomEvent('azari:form-error', {
            bubbles: true,
            detail: { error },
        }));
    } finally {
        setBusy(form, false);
    }
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.matches('[data-demo-form]')) return;

    const submitter = event.submitter instanceof HTMLElement
        ? event.submitter
        : form.querySelector(SELECTORS.submitter);

    if (!form.checkValidity()) {
        setBusy(form, false);
        return;
    }

    const confirmation = submitter?.dataset.confirm || form.dataset.confirm;
    const alreadyConfirmed = form.dataset.azariConfirmed === 'true';

    if (confirmation && !alreadyConfirmed) {
        event.preventDefault();

        const accepted = await requestConfirmation(confirmation, {
            confirmLabel: submitter?.dataset.confirmLabel || form.dataset.confirmLabel || 'Continue',
            cancelLabel: submitter?.dataset.cancelLabel || form.dataset.cancelLabel || 'Cancel',
            destructive:
                submitter?.dataset.confirmVariant === 'destructive'
                || form.dataset.confirmVariant === 'destructive',
        });

        if (!accepted) {
            setBusy(form, false);
            return;
        }

        form.dataset.azariConfirmed = 'true';

        if (form.matches('[data-async-form]')) {
            await submitAsync(form, submitter);
            delete form.dataset.azariConfirmed;
            return;
        }

        form.requestSubmit(submitter);
        window.setTimeout(() => delete form.dataset.azariConfirmed, 0);
        return;
    }

    if (form.matches('[data-async-form]')) {
        event.preventDefault();
        await submitAsync(form, submitter);
        delete form.dataset.azariConfirmed;
        return;
    }

    setBusy(form, true, submitter);
    delete form.dataset.azariConfirmed;
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[aria-busy="true"]').forEach((form) => {
        setBusy(form, false);
    });
});

document.addEventListener('click', async (event) => {
    const toggle = event.target.closest('[data-toggle-target]');
    if (toggle) {
        const target = document.getElementById(toggle.dataset.toggleTarget);
        if (!target) return;

        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        target.hidden = expanded;
        target.setAttribute('aria-hidden', expanded ? 'true' : 'false');

        if (!expanded) target.querySelector('input, select, textarea, button, a[href]')?.focus();
        return;
    }

    const copy = event.target.closest('[data-copy]');
    if (!copy) return;

    const source = copy.dataset.copy;
    const target = source?.startsWith('#') ? document.querySelector(source) : null;
    const value = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement
        ? target.value
        : target?.textContent || source || '';

    try {
        await navigator.clipboard.writeText(value.trim());
        toast(copy.dataset.copySuccess || 'Copied to clipboard.', 'success');
    } catch {
        toast('Copying failed. Select the value and copy it manually.', 'error');
    }
});

document.querySelectorAll('[data-toggle-target]').forEach((toggle) => {
    const target = document.getElementById(toggle.dataset.toggleTarget);
    if (!target) return;

    toggle.setAttribute('aria-controls', target.id);
    toggle.setAttribute('aria-expanded', target.hidden ? 'false' : 'true');
    target.setAttribute('aria-hidden', target.hidden ? 'true' : 'false');
});
