// Admin voucher preview. The input stays read-only to prevent accidental edits.
// The backend provides a secure initial code and validates uniqueness on save.
(() => {
    const init = () => {
        const input = document.querySelector('[data-voucher-code-preview]');
        const button = document.querySelector('[data-voucher-code-regenerate]');
        if (!(input instanceof HTMLInputElement) || !(button instanceof HTMLButtonElement)) return;

        const regenerate = () => {
            if (!window.crypto?.getRandomValues) return;
            const random = new Uint8Array(9);
            window.crypto.getRandomValues(random);
            input.value = 'RSV-' + Array.from(random, byte => byte.toString(16).padStart(2, '0')).join('').toUpperCase();
        };

        // Preserve the original code after a validation failure, so form input
        // and error messages still refer to the same attempted voucher.
        if (input.dataset.restored !== '1') regenerate();
        button.addEventListener('click', regenerate);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
