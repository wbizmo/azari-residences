const AzariDatePickerV3 = (() => {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
    const pad = (value) => String(value).padStart(2, '0');

    const toISO = (date) =>
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

    const parseISO = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
        const [year, month, day] = value.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const formatDisplay = (value) => {
        const date = parseISO(value);
        if (!date) return '';
        return new Intl.DateTimeFormat(document.documentElement.lang || 'en', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(date);
    };

    const mondayIndex = (date) => {
        const day = date.getDay();
        return day === 0 ? 6 : day - 1;
    };

    const isDisabled = (date, input) => {
        const min = parseISO(input.min);
        const max = parseISO(input.max);
        return Boolean((min && date < min) || (max && date > max));
    };

    const icon = (name) => {
        const node = document.createElement('span');
        node.className = 'material-symbols-outlined az-date-v3__icon';
        node.setAttribute('aria-hidden', 'true');
        node.textContent = name;
        return node;
    };

    const closeAllExcept = (current) => {
        document.querySelectorAll('.az-date-v3.is-open').forEach((picker) => {
            if (picker === current) return;
            picker.classList.remove('is-open');
            const panel = picker.querySelector('.az-date-v3__panel');
            const trigger = picker.querySelector('.az-date-v3__trigger');
            if (panel) panel.hidden = true;
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    };

    const enhance = (input) => {
        if (!(input instanceof HTMLInputElement)) return;
        if (input.type !== 'date' || input.dataset.azDateV3Ready === 'true') return;

        input.dataset.azDateV3Ready = 'true';
        input.classList.add('az-date-v3__native');

        const root = document.createElement('div');
        root.className = 'az-date-v3';
        input.parentNode.insertBefore(root, input);
        root.appendChild(input);

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'az-date-v3__trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');

        const value = document.createElement('span');
        value.className = 'az-date-v3__value';
        value.textContent = formatDisplay(input.value) || input.placeholder || 'Select date';

        trigger.append(icon('calendar_month'), value);

        const panel = document.createElement('div');
        panel.className = 'az-date-v3__panel';
        panel.hidden = true;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Choose date');

        const header = document.createElement('div');
        header.className = 'az-date-v3__header';

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'az-date-v3__nav';
        prev.setAttribute('aria-label', 'Previous month');
        prev.appendChild(icon('chevron_left'));

        const title = document.createElement('div');
        title.className = 'az-date-v3__title';
        title.setAttribute('aria-live', 'polite');

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'az-date-v3__nav';
        next.setAttribute('aria-label', 'Next month');
        next.appendChild(icon('chevron_right'));

        header.append(prev, title, next);

        const weekdayRow = document.createElement('div');
        weekdayRow.className = 'az-date-v3__weekdays';
        weekdays.forEach((weekday) => {
            const cell = document.createElement('span');
            cell.textContent = weekday;
            weekdayRow.appendChild(cell);
        });

        const grid = document.createElement('div');
        grid.className = 'az-date-v3__grid';

        const footer = document.createElement('div');
        footer.className = 'az-date-v3__footer';

        const today = document.createElement('button');
        today.type = 'button';
        today.className = 'az-date-v3__text-button';
        today.textContent = 'Today';

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'az-date-v3__text-button';
        clear.textContent = 'Clear';

        footer.append(today, clear);
        panel.append(header, weekdayRow, grid, footer);
        root.append(trigger, panel);

        let cursor = parseISO(input.value) || new Date();
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

        const close = () => {
            root.classList.remove('is-open');
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        };

        const commit = (date) => {
            input.value = date ? toISO(date) : '';
            value.textContent = formatDisplay(input.value) || input.placeholder || 'Select date';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            render();
            close();
            trigger.focus();
        };

        const render = () => {
            title.textContent = `${months[cursor.getMonth()]} ${cursor.getFullYear()}`;
            grid.replaceChildren();

            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const start = new Date(first);
            start.setDate(first.getDate() - mondayIndex(first));

            const selected = parseISO(input.value);
            const now = new Date();

            for (let index = 0; index < 42; index += 1) {
                const date = new Date(start);
                date.setDate(start.getDate() + index);

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'az-date-v3__day';
                button.textContent = String(date.getDate());

                if (date.getMonth() !== cursor.getMonth()) {
                    button.classList.add('is-outside');
                }
                if (toISO(date) === toISO(now)) {
                    button.classList.add('is-today');
                }
                if (selected && toISO(date) === toISO(selected)) {
                    button.classList.add('is-selected');
                    button.setAttribute('aria-current', 'date');
                }
                if (isDisabled(date, input)) {
                    button.disabled = true;
                }

                button.addEventListener('click', () => commit(date));
                grid.appendChild(button);
            }
        };

        trigger.addEventListener('click', () => {
            const opening = panel.hidden;
            closeAllExcept(root);
            panel.hidden = !opening;
            root.classList.toggle('is-open', opening);
            trigger.setAttribute('aria-expanded', String(opening));
            if (opening) render();
        });

        prev.addEventListener('click', () => {
            cursor.setMonth(cursor.getMonth() - 1);
            render();
        });

        next.addEventListener('click', () => {
            cursor.setMonth(cursor.getMonth() + 1);
            render();
        });

        today.addEventListener('click', () => commit(new Date()));
        clear.addEventListener('click', () => commit(null));

        input.addEventListener('change', () => {
            const parsed = parseISO(input.value);
            if (parsed) cursor = new Date(parsed.getFullYear(), parsed.getMonth(), 1);
            value.textContent = formatDisplay(input.value) || input.placeholder || 'Select date';
        });

        panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                trigger.focus();
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (!root.contains(event.target)) close();
        });

        render();
    };

    const initialise = (scope = document) => {
        scope.querySelectorAll('input[type="date"]').forEach(enhance);
    };

    return { initialise, enhance };
})();

const bootAzariDatePickerV3 = () => {
    AzariDatePickerV3.initialise();

    new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('input[type="date"]')) AzariDatePickerV3.enhance(node);
                AzariDatePickerV3.initialise(node);
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAzariDatePickerV3);
} else {
    bootAzariDatePickerV3();
}

window.AzariDatePickerV3 = AzariDatePickerV3;
