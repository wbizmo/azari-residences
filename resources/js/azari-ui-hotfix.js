const AzariUI = (() => {
    const ensureToastRegion = () => {
        let region = document.querySelector('[data-az-toast-region]');
        if (region) return region;

        region = document.createElement('div');
        region.className = 'az-toast-region';
        region.dataset.azToastRegion = '';
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-atomic', 'false');
        document.body.appendChild(region);
        return region;
    };

    const toast = (message, type = 'info', duration = 4500) => {
        const region = ensureToastRegion();
        const item = document.createElement('div');
        item.className = `az-toast az-toast--${type}`;
        item.setAttribute('role', type === 'error' ? 'alert' : 'status');
        item.innerHTML = `
            <span class="material-symbols-outlined" aria-hidden="true">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info'}</span>
            <p></p>
            <button type="button" class="az-toast__close" aria-label="Dismiss notification">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>`;
        item.querySelector('p').textContent = String(message || '');
        const remove = () => {
            item.classList.add('is-leaving');
            window.setTimeout(() => item.remove(), 180);
        };
        item.querySelector('button').addEventListener('click', remove);
        region.appendChild(item);
        window.setTimeout(remove, duration);
    };

    const confirmAction = ({ title = 'Please confirm', message = 'Continue with this action?', confirmLabel = 'Continue', destructive = false } = {}) => {
        return new Promise((resolve) => {
            const previousFocus = document.activeElement;
            const overlay = document.createElement('div');
            overlay.className = 'az-modal-overlay';
            overlay.dataset.azModal = '';
            overlay.innerHTML = `
                <section class="az-modal" role="dialog" aria-modal="true" aria-labelledby="az-confirm-title" aria-describedby="az-confirm-message">
                    <header class="az-modal__header">
                        <span class="az-modal__icon material-symbols-outlined" aria-hidden="true">${destructive ? 'warning' : 'help'}</span>
                        <div>
                            <p class="az-modal__eyebrow">Reserva</p>
                            <h2 id="az-confirm-title"></h2>
                        </div>
                        <button type="button" class="az-modal__close" data-az-modal-cancel aria-label="Close confirmation">
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                        </button>
                    </header>
                    <div class="az-modal__body"><p id="az-confirm-message"></p></div>
                    <footer class="az-modal__footer">
                        <button type="button" class="az-button az-button--secondary" data-az-modal-cancel>Cancel</button>
                        <button type="button" class="az-button ${destructive ? 'az-button--danger' : 'az-button--primary'}" data-az-modal-confirm></button>
                    </footer>
                </section>`;

            overlay.querySelector('#az-confirm-title').textContent = title;
            overlay.querySelector('#az-confirm-message').textContent = message;
            overlay.querySelector('[data-az-modal-confirm]').textContent = confirmLabel;
            document.body.appendChild(overlay);
            document.body.classList.add('az-modal-open');

            const focusable = [...overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            const close = (answer) => {
                overlay.removeEventListener('keydown', onKeydown);
                overlay.classList.add('is-closing');
                window.setTimeout(() => overlay.remove(), 160);
                document.body.classList.remove('az-modal-open');
                if (previousFocus instanceof HTMLElement) previousFocus.focus();
                resolve(answer);
            };

            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close(false);
                }
                if (event.key === 'Tab' && focusable.length) {
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            };

            overlay.addEventListener('keydown', onKeydown);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close(false);
            });
            overlay.querySelectorAll('[data-az-modal-cancel]').forEach((button) => button.addEventListener('click', () => close(false)));
            overlay.querySelector('[data-az-modal-confirm]').addEventListener('click', () => close(true));
            window.requestAnimationFrame(() => first?.focus());
        });
    };

    const bindConfirmations = () => {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-az-confirm]');
            if (!form || form.dataset.azConfirmed === 'true') return;
            event.preventDefault();
            const accepted = await confirmAction({
                title: form.dataset.azConfirmTitle || 'Confirm action',
                message: form.dataset.azConfirm || 'Continue with this action?',
                confirmLabel: form.dataset.azConfirmLabel || 'Continue',
                destructive: form.dataset.azConfirmType === 'danger',
            });
            if (!accepted) return;
            form.dataset.azConfirmed = 'true';
            form.requestSubmit();
        }, true);

        document.addEventListener('click', async (event) => {
            const link = event.target.closest('a[data-az-confirm]');
            if (!link) return;
            event.preventDefault();
            const accepted = await confirmAction({
                title: link.dataset.azConfirmTitle || 'Confirm action',
                message: link.dataset.azConfirm || 'Continue with this action?',
                confirmLabel: link.dataset.azConfirmLabel || 'Continue',
                destructive: link.dataset.azConfirmType === 'danger',
            });
            if (accepted) window.location.assign(link.href);
        });
    };

    return { toast, confirmAction, bindConfirmations };
})();

window.AzariUI = AzariUI;
document.addEventListener('DOMContentLoaded', () => AzariUI.bindConfirmations());
