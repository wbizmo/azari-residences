// Shared submit/action state. Checkbox rendering is handled by CSS, not DOM injection.
// Adding a second track here duplicated authored admin and customer switches.
const AzariProductionHotfix = (() => {
    const labels = new WeakMap();

    const working = (button) => {
        if (!(button instanceof HTMLElement) || button.dataset.azWorking === 'true') return;

        button.dataset.azWorking = 'true';
        button.setAttribute('aria-busy', 'true');

        const label = button.querySelector('[data-az-button-label]');
        labels.set(button, label ? label.textContent : button instanceof HTMLInputElement ? button.value : button.textContent);
        const text = button.dataset.azWorkingText || 'Working…';

        if (label) label.textContent = text;
        else if (button instanceof HTMLInputElement) button.value = text;
        else button.textContent = text;

        if ('disabled' in button) button.disabled = true;
    };

    const reset = (button) => {
        if (!(button instanceof HTMLElement) || button.dataset.azWorking !== 'true') return;

        const original = labels.get(button);
        const label = button.querySelector('[data-az-button-label]');
        if (typeof original === 'string') {
            if (label) label.textContent = original;
            else if (button instanceof HTMLInputElement) button.value = original;
            else button.textContent = original;
        }

        button.removeAttribute('aria-busy');
        delete button.dataset.azWorking;
        if ('disabled' in button) button.disabled = false;
    };

    const init = () => {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            const submitter = event.submitter || form.querySelector('button[type="submit"],input[type="submit"]');
            if (!(submitter instanceof HTMLElement)) return;
            if (!form.checkValidity()) {
                reset(submitter);
                return;
            }
            working(submitter);
        }, true);

        document.addEventListener('click', (event) => {
            const action = event.target.closest('[data-az-stateful-action]');
            if (action && !event.defaultPrevented) working(action);
        });

        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-az-working="true"]').forEach(reset);
        });
    };

    return { init };
})();

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', AzariProductionHotfix.init)
    : AzariProductionHotfix.init();

window.AzariProductionHotfix = AzariProductionHotfix;
