const focusableSelector = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
let activeModal = null;
let previousFocus = null;

function openModal(modal, trigger) {
    if (!modal) return;
    previousFocus = document.activeElement;
    const form = modal.querySelector('[data-az-modal-form]');
    const title = modal.querySelector('[data-az-modal-title]');
    const message = modal.querySelector('[data-az-modal-message]');
    const confirmButton = modal.querySelector('[data-az-modal-confirm]');
    const method = modal.querySelector('[data-az-modal-method]');
    const reason = modal.querySelector('[data-az-modal-reason]');
    const status = modal.querySelector('[data-az-modal-status]');

    form.action = trigger.dataset.action || '#';
    title.textContent = trigger.dataset.title || 'Confirm action';
    message.textContent = trigger.dataset.message || 'Review this action before continuing.';
    confirmButton.textContent = trigger.dataset.confirmLabel || 'Continue';
    method.value = trigger.dataset.method || 'PUT';
    reason.value = trigger.dataset.reason || '';
    status.value = trigger.dataset.status || '';

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('az-modal-open');
    activeModal = modal;
    requestAnimationFrame(() => modal.querySelector('.az-modal__dialog')?.focus());
}

function closeModal(modal) {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('az-modal-open');
    activeModal = null;
    previousFocus?.focus?.();
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-az-modal-open]');
    if (trigger) {
        openModal(document.getElementById(trigger.dataset.azModalOpen), trigger);
        return;
    }

    const bookingTrigger = event.target.closest('[data-az-booking-modal]');
    if (bookingTrigger) {
        const select = document.getElementById(bookingTrigger.dataset.select);
        const status = select?.value || '';
        const label = select?.selectedOptions?.[0]?.textContent?.trim() || status;
        bookingTrigger.dataset.status = status;
        bookingTrigger.dataset.method = 'PUT';
        bookingTrigger.dataset.title = 'Update booking status';
        bookingTrigger.dataset.message = `Change ${bookingTrigger.dataset.reference} to ${label}? This action will be recorded in the booking lifecycle.`;
        bookingTrigger.dataset.confirmLabel = status === 'cancelled' ? 'Cancel booking' : 'Update status';
        openModal(document.getElementById(bookingTrigger.dataset.azBookingModal), bookingTrigger);
        return;
    }

    const close = event.target.closest('[data-az-modal-close]');
    if (close) closeModal(close.closest('[data-az-modal]'));

    const toastClose = event.target.closest('[data-az-toast-close]');
    if (toastClose) toastClose.closest('[data-az-toast]')?.remove();
});

document.addEventListener('keydown', (event) => {
    if (!activeModal) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        closeModal(activeModal);
        return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...activeModal.querySelectorAll(focusableSelector)];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
});

setTimeout(() => {
    document.querySelectorAll('[data-az-toast]').forEach((toast) => toast.remove());
}, 6500);
