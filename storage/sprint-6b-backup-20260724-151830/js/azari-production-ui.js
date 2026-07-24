const AzariProductionUI = (() => {
    const originals = new WeakMap();

    const workingText = (button) => {
        if (button.dataset.workingText) return button.dataset.workingText;
        const text = (button.textContent || '').trim();
        if (/sign\s*in|log\s*in/i.test(text)) return 'Signing in…';
        if (/register|create account/i.test(text)) return 'Creating account…';
        if (/book|reserve|request/i.test(text)) return 'Processing…';
        if (/save|update|apply/i.test(text)) return 'Saving…';
        if (/delete|remove/i.test(text)) return 'Removing…';
        if (/send|submit/i.test(text)) return 'Sending…';
        return 'Working…';
    };

    const setWorking = (button) => {
        if (!button || button.dataset.azWorking === 'true') return;
        originals.set(button, button.innerHTML);
        button.dataset.azWorking = 'true';
        button.setAttribute('aria-busy', 'true');
        if ('disabled' in button) button.disabled = true;
        button.classList.add('is-working');
        button.innerHTML = `<span class="az-action-spinner" aria-hidden="true"></span><span>${workingText(button)}</span>`;
    };

    const resetWorking = (button, state = '') => {
        if (!button || !originals.has(button)) return;
        button.innerHTML = originals.get(button);
        originals.delete(button);
        if ('disabled' in button) button.disabled = false;
        button.removeAttribute('aria-busy');
        button.dataset.azWorking = 'false';
        button.classList.remove('is-working', 'is-success', 'is-error');
        if (state) {
            button.classList.add(`is-${state}`);
            window.setTimeout(() => button.classList.remove(`is-${state}`), 1600);
        }
    };

    const enhanceCheckbox = (input) => {
        if (!(input instanceof HTMLInputElement) || input.type !== 'checkbox' || input.dataset.azToggleReady === 'true') return;
        input.dataset.azToggleReady = 'true';
        const label = input.closest('label');
        if (label) label.classList.add('az-toggle-label');
        const toggle = document.createElement('span');
        toggle.className = 'az-toggle';
        toggle.setAttribute('aria-hidden', 'true');
        toggle.innerHTML = '<span class="az-toggle__thumb"></span>';
        input.insertAdjacentElement('afterend', toggle);
    };

    const enhance = (root = document) => root.querySelectorAll('input[type="checkbox"]').forEach(enhanceCheckbox);

    const init = () => {
        enhance();

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!(submitter instanceof HTMLElement)) return;
            requestAnimationFrame(() => form.checkValidity() ? setWorking(submitter) : resetWorking(submitter, 'error'));
        }, true);

        document.addEventListener('click', (event) => {
            const action = event.target.closest('[data-az-stateful], a.az-button[href], a.button[href]');
            if (!action || action.matches('[href^="#"], [href^="javascript:"], [data-modal-open], [data-az-modal-open]')) return;
            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            setWorking(action);
        });

        window.addEventListener('pageshow', () => document.querySelectorAll('[data-az-working="true"]').forEach(resetWorking));
        document.addEventListener('azari:action-success', (e) => resetWorking(e.detail?.button, 'success'));
        document.addEventListener('azari:action-error', (e) => resetWorking(e.detail?.button, 'error'));

        new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) return;
            if (node.matches('input[type="checkbox"]')) enhanceCheckbox(node);
            enhance(node);
        }))).observe(document.documentElement, { childList: true, subtree: true });
    };

    return { init, setWorking, resetWorking, enhance };
})();

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', AzariProductionUI.init)
    : AzariProductionUI.init();

window.AzariProductionUI = AzariProductionUI;
