const userBody = document.querySelector('.az-user-body');
const openButton = document.querySelector('[data-user-drawer-open]');
const closeButtons = document.querySelectorAll('[data-user-drawer-close]');

const setDrawer = (open) => {
    if (!userBody) return;
    userBody.classList.toggle('az-user-drawer-open', open);
    openButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) document.querySelector('.az-user-mobile-drawer a, .az-user-drawer-close')?.focus();
};
openButton?.addEventListener('click', () => setDrawer(true));
closeButtons.forEach((button) => button.addEventListener('click', () => setDrawer(false)));
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setDrawer(false); });

const detectedTimezone = (() => {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch { return ''; }
})();
document.querySelectorAll('[data-user-timezone]').forEach((node) => {
    const fallback = node.dataset.fallbackTimezone || 'Africa/Lagos';
    node.textContent = detectedTimezone || fallback;
});
document.querySelectorAll('input[name="timezone"]').forEach((input) => {
    if (!input.value && detectedTimezone) input.value = detectedTimezone;
});

document.querySelectorAll('[data-user-local-time]').forEach((node) => {
    const iso = node.dataset.userLocalTime;
    if (!iso) return;
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return;

    const mode = node.dataset.userTimeFormat || 'datetime';
    const options = mode === 'date'
        ? { day: 'numeric', month: 'short', year: 'numeric' }
        : mode === 'long-date'
            ? { day: 'numeric', month: 'long', year: 'numeric' }
            : mode === 'time'
                ? { hour: 'numeric', minute: '2-digit' }
                : { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' };

    try {
        node.textContent = new Intl.DateTimeFormat(undefined, options).format(date);
        node.title = detectedTimezone
            ? `Times are shown in your local timezone: ${detectedTimezone}`
            : `Times are shown in Azari's operational timezone: ${node.dataset.fallbackTimezone || 'Africa/Lagos'}`;
    } catch {
        // Keep the server-rendered Azari operational-time fallback.
    }
});
